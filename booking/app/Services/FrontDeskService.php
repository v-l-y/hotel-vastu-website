<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\Stay;
use App\Models\StayGuest;
use App\Models\StayRoom;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FrontDeskService
{
    public function __construct(
        private FolioService $folios,
        private InvoiceService $invoices
    ) {
    }

    public function checkIn(Reservation $reservation, array $roomIds): Stay
    {
        return DB::transaction(function () use ($reservation, $roomIds) {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->status !== 'confirmed') {
                throw new RuntimeException('Only confirmed reservations can be checked in.');
            }

            if ($reservation->pricing_status !== 'priced') {
                throw new RuntimeException('Reservation pricing must be finalized before check-in.');
            }

            $today = today();
            if ($today->lt($reservation->check_in_date) || ! $today->lt($reservation->check_out_date)) {
                throw new RuntimeException('Check-in is only allowed during the reserved stay window.');
            }

            if (Stay::query()->where('reservation_id', $reservation->id)->exists()) {
                throw new RuntimeException('This reservation already has a stay record.');
            }

            $reservation->load(['rooms', 'guestLinks']);
            $expectedByType = $reservation->rooms
                ->groupBy('room_type_id')
                ->map(fn ($rows) => (int) $rows->sum('quantity'));

            $roomIds = array_values(array_unique(array_map('intval', $roomIds)));
            if (count($roomIds) !== (int) $expectedByType->sum()) {
                throw new RuntimeException('The number of assigned rooms must match the reservation.');
            }

            $rooms = Room::query()->whereIn('id', $roomIds)->lockForUpdate()->get();
            if ($rooms->count() !== count($roomIds)) {
                throw new RuntimeException('One or more selected rooms do not exist.');
            }

            foreach ($expectedByType as $roomTypeId => $requiredCount) {
                $actual = $rooms->where('room_type_id', (int) $roomTypeId)->where('status', 'active')->count();
                if ($actual !== $requiredCount) {
                    throw new RuntimeException('Assigned physical rooms do not match the reserved room types.');
                }
            }

            $occupied = StayRoom::query()
                ->whereIn('room_id', $roomIds)
                ->whereNull('released_at')
                ->whereHas('stay', fn ($q) => $q->where('status', 'checked_in'))
                ->exists();

            if ($occupied) {
                throw new RuntimeException('One or more selected rooms are already occupied.');
            }

            $blocked = RoomBlock::query()
                ->whereIn('room_id', $roomIds)
                ->where('status', 'active')
                ->whereDate('starts_on', '<', $reservation->check_out_date->toDateString())
                ->whereDate('ends_on', '>', $reservation->check_in_date->toDateString())
                ->exists();

            if ($blocked) {
                throw new RuntimeException('One or more selected rooms are blocked during this stay.');
            }

            $stay = Stay::query()->create([
                'reservation_id' => $reservation->id,
                'status' => 'checked_in',
                'checked_in_at' => now(),
            ]);

            foreach ($rooms as $room) {
                StayRoom::query()->create([
                    'stay_id' => $stay->id,
                    'room_id' => $room->id,
                    'assigned_at' => now(),
                ]);
            }

            foreach ($reservation->guestLinks as $guestLink) {
                StayGuest::query()->create([
                    'stay_id' => $stay->id,
                    'guest_id' => $guestLink->guest_id,
                    'role' => $guestLink->role,
                ]);
            }

            $folio = Folio::query()->create([
                'stay_id' => $stay->id,
                'reservation_id' => $reservation->id,
                'status' => 'open',
            ]);

            $this->folios->addCharge($folio, [
                'category' => 'room',
                'description' => 'Room reservation '.$reservation->booking_number,
                'quantity' => 1,
                'subtotal' => $reservation->subtotal,
                'tax' => $reservation->tax,
                'amount' => $reservation->total,
                'source_key' => 'reservation:'.$reservation->id,
            ]);

            Payment::query()
                ->where('reservation_id', $reservation->id)
                ->whereNull('folio_id')
                ->update(['folio_id' => $folio->id]);

            $this->folios->recalculate($folio->fresh());
            $reservation->update(['status' => 'checked_in']);

            return $stay->fresh(['rooms.room', 'guests', 'folio']);
        }, 3);
    }

    public function checkOut(Stay $stay): array
    {
        return DB::transaction(function () use ($stay) {
            $stay = Stay::query()->whereKey($stay->id)->lockForUpdate()->firstOrFail();

            if ($stay->status !== 'checked_in') {
                throw new RuntimeException('Only an active stay can be checked out.');
            }

            $folio = Folio::query()->where('stay_id', $stay->id)->lockForUpdate()->firstOrFail();
            $folio = $this->folios->recalculate($folio);

            if ((float) $folio->balance > 0.009) {
                throw new RuntimeException('The folio must be fully settled before checkout.');
            }

            $invoice = $this->invoices->createFromFolio($folio);

            $folio->update(['status' => 'closed']);
            StayRoom::query()->where('stay_id', $stay->id)->whereNull('released_at')->update(['released_at' => now()]);
            $stay->update(['status' => 'checked_out', 'checked_out_at' => now()]);
            Reservation::query()->whereKey($stay->reservation_id)->update(['status' => 'checked_out']);

            return [
                'stay' => $stay->fresh(),
                'folio' => $folio->fresh(),
                'invoice' => $invoice,
            ];
        }, 3);
    }
}
