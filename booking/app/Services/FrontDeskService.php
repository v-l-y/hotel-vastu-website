<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationNightRate;
use App\Models\RestaurantOrder;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomBlock;
use App\Models\Stay;
use App\Models\StayGuest;
use App\Models\StayRoom;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FrontDeskService
{
    public function __construct(
        private FolioService $folios,
        private InvoiceService $invoices,
        private AvailabilityService $availability,
        private PricingService $pricing
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
                $actual = $rooms
                    ->where('room_type_id', (int) $roomTypeId)
                    ->where('status', 'active')
                    ->filter(fn ($room) => in_array($room->housekeeping_status, ['clean', 'inspected'], true))
                    ->count();

                if ($actual !== $requiredCount) {
                    throw new RuntimeException('Assigned rooms must match the reserved room types and be ready for guests.');
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
                ->update([
                    'folio_id' => $folio->id,
                    'reservation_id' => null,
                ]);

            $this->folios->recalculate($folio->fresh());
            $reservation->update(['status' => 'checked_in']);

            return $stay->fresh(['rooms.room', 'guests', 'folio']);
        }, 3);
    }

    public function transferRoom(Stay $stay, int $fromRoomId, int $toRoomId): Stay
    {
        return DB::transaction(function () use ($stay, $fromRoomId, $toRoomId) {
            $stay = Stay::query()->whereKey($stay->id)->lockForUpdate()->firstOrFail();
            if ($stay->status !== 'checked_in') {
                throw new RuntimeException('Room transfers require an active checked-in stay.');
            }

            $reservation = Reservation::query()->findOrFail($stay->reservation_id);
            $assignment = StayRoom::query()
                ->where('stay_id', $stay->id)
                ->where('room_id', $fromRoomId)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->firstOrFail();

            $fromRoom = Room::query()->whereKey($fromRoomId)->lockForUpdate()->firstOrFail();
            $toRoom = Room::query()->whereKey($toRoomId)->lockForUpdate()->firstOrFail();

            if ($fromRoom->room_type_id !== $toRoom->room_type_id) {
                throw new RuntimeException('Room transfer must use the same reserved room type.');
            }

            if ($toRoom->status !== 'active' || ! in_array($toRoom->housekeeping_status, ['clean', 'inspected'], true)) {
                throw new RuntimeException('Target room is not ready for guests.');
            }

            $occupied = StayRoom::query()
                ->where('room_id', $toRoom->id)
                ->whereNull('released_at')
                ->whereHas('stay', fn ($q) => $q->where('status', 'checked_in'))
                ->exists();

            if ($occupied) {
                throw new RuntimeException('Target room is already occupied.');
            }

            $blocked = RoomBlock::query()
                ->where('room_id', $toRoom->id)
                ->where('status', 'active')
                ->whereDate('starts_on', '<', $reservation->check_out_date->toDateString())
                ->whereDate('ends_on', '>', today()->toDateString())
                ->exists();

            if ($blocked) {
                throw new RuntimeException('Target room is blocked during the remaining stay.');
            }

            $assignment->update(['released_at' => now()]);
            $fromRoom->update(['housekeeping_status' => 'dirty']);

            StayRoom::query()->create([
                'stay_id' => $stay->id,
                'room_id' => $toRoom->id,
                'assigned_at' => now(),
            ]);

            return $stay->fresh(['rooms.room', 'folio']);
        }, 3);
    }

    public function extendStay(Stay $stay, CarbonImmutable $newCheckout): Stay
    {
        return DB::transaction(function () use ($stay, $newCheckout) {
            $stay = Stay::query()->whereKey($stay->id)->lockForUpdate()->firstOrFail();
            if ($stay->status !== 'checked_in') {
                throw new RuntimeException('Only an active stay can be extended.');
            }

            $reservation = Reservation::query()->whereKey($stay->reservation_id)->lockForUpdate()->firstOrFail();
            $oldCheckout = CarbonImmutable::parse($reservation->check_out_date);

            if ($newCheckout->lessThanOrEqualTo($oldCheckout)) {
                throw new RuntimeException('New checkout must be after the current checkout date.');
            }

            $reservation->load('rooms');

            $roomTypeIds = $reservation->rooms
                ->pluck('room_type_id')
                ->unique()
                ->sort()
                ->values();

            RoomType::query()
                ->whereIn('id', $roomTypeIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($reservation->rooms as $reservationRoom) {
                $availability = $this->availability->forRoomType(
                    $reservationRoom->room_type_id,
                    $oldCheckout,
                    $newCheckout
                );

                if ($availability['available_rooms'] < $reservationRoom->quantity) {
                    throw new RuntimeException('The reserved room type is not available for the requested extension.');
                }
            }

            $activeRoomIds = StayRoom::query()
                ->where('stay_id', $stay->id)
                ->whereNull('released_at')
                ->pluck('room_id');

            $blocked = RoomBlock::query()
                ->whereIn('room_id', $activeRoomIds)
                ->where('status', 'active')
                ->whereDate('starts_on', '<', $newCheckout->toDateString())
                ->whereDate('ends_on', '>', $oldCheckout->toDateString())
                ->exists();

            if ($blocked) {
                throw new RuntimeException('One or more assigned rooms are blocked during the extension.');
            }

            $extraSubtotal = 0.0;
            $extraTax = 0.0;
            $totalStayNights = CarbonImmutable::parse($reservation->check_in_date)
                ->diffInDays($newCheckout);

            foreach ($reservation->rooms as $reservationRoom) {
                if ($reservationRoom->rate_plan_id === null) {
                    throw new RuntimeException('A rate plan is required to extend this stay.');
                }

                $quote = $this->pricing->quote(
                    $reservationRoom->room_type_id,
                    $reservationRoom->rate_plan_id,
                    $oldCheckout,
                    $newCheckout,
                    $reservationRoom->quantity,
                    $totalStayNights
                );

                foreach ($quote['nights'] as $night) {
                    ReservationNightRate::query()->create([
                        'reservation_id' => $reservation->id,
                        'reservation_room_id' => $reservationRoom->id,
                        'room_type_id' => $reservationRoom->room_type_id,
                        'rate_plan_id' => $reservationRoom->rate_plan_id,
                        'stay_date' => $night['stay_date'],
                        'quantity' => $night['quantity'],
                        'unit_rate' => $night['unit_rate'],
                        'line_total' => $night['line_total'],
                        'tax_rate' => $night['tax_rate'],
                        'tax_amount' => $night['tax_amount'],
                        'gross_total' => $night['gross_total'],
                    ]);
                }

                $extraSubtotal = round($extraSubtotal + $quote['subtotal'], 2);
                $extraTax = round($extraTax + $quote['tax'], 2);
            }

            $extraTotal = round($extraSubtotal + $extraTax, 2);
            $reservation->update([
                'check_out_date' => $newCheckout->toDateString(),
                'subtotal' => round((float) $reservation->subtotal + $extraSubtotal, 2),
                'tax' => round((float) $reservation->tax + $extraTax, 2),
                'total' => round((float) $reservation->total + $extraTotal, 2),
            ]);

            $folio = Folio::query()->where('stay_id', $stay->id)->lockForUpdate()->firstOrFail();
            $this->folios->addCharge($folio, [
                'category' => 'room',
                'description' => 'Stay extension through '.$newCheckout->toDateString(),
                'quantity' => 1,
                'subtotal' => $extraSubtotal,
                'tax' => $extraTax,
                'amount' => $extraTotal,
                'source_key' => 'stay-extension:'.$stay->id.':'.$newCheckout->toDateString(),
            ]);

            return $stay->fresh(['reservation', 'rooms.room', 'folio']);
        }, 3);
    }

    public function updateHousekeeping(Room $room, string $status): Room
    {
        if (! in_array($status, ['clean', 'dirty', 'inspected', 'out_of_order'], true)) {
            throw new RuntimeException('Unsupported housekeeping status.');
        }

        $room->update(['housekeeping_status' => $status]);

        return $room->fresh();
    }

    public function checkOut(Stay $stay): array
    {
        return DB::transaction(function () use ($stay) {
            $stay = Stay::query()->whereKey($stay->id)->lockForUpdate()->firstOrFail();

            if ($stay->status !== 'checked_in') {
                throw new RuntimeException('Only an active stay can be checked out.');
            }

            $folio = Folio::query()->where('stay_id', $stay->id)->lockForUpdate()->firstOrFail();

            $activeRoomService = RestaurantOrder::query()
                ->where('folio_id', $folio->id)
                ->where('order_type', 'room_service')
                ->whereIn('status', ['accepted', 'preparing', 'ready'])
                ->exists();

            if ($activeRoomService) {
                throw new RuntimeException('Complete or cancel pending room-service orders before checkout.');
            }

            $folio = $this->folios->recalculate($folio);

            if (abs((float) $folio->balance) > 0.009) {
                throw new RuntimeException('The folio must be fully settled before checkout.');
            }

            $invoice = $this->invoices->createFromFolio($folio);

            $activeAssignments = StayRoom::query()
                ->where('stay_id', $stay->id)
                ->whereNull('released_at')
                ->get();

            foreach ($activeAssignments as $assignment) {
                Room::query()->whereKey($assignment->room_id)->update(['housekeeping_status' => 'dirty']);
                $assignment->update(['released_at' => now()]);
            }

            $folio->update(['status' => 'closed']);
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
