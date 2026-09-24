<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReservationLifecycleService
{
    public function __construct(
        private AvailabilityService $availability,
        private PricingService $pricing
    ) {
    }

    public function modifyConfirmed(
        Reservation $reservation,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $roomTypeId,
        int $ratePlanId,
        int $quantity,
        int $adults,
        int $children
    ): Reservation {
        return DB::transaction(function () use (
            $reservation, $checkIn, $checkOut, $roomTypeId, $ratePlanId,
            $quantity, $adults, $children
        ) {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->status !== 'confirmed') {
                throw new RuntimeException('Only confirmed pre-arrival reservations can be modified.');
            }

            if ($checkIn->isBefore(today()) || $checkOut->lessThanOrEqualTo($checkIn)) {
                throw new RuntimeException('Choose a valid future stay window.');
            }

            $reservation->load('rooms');
            if ($reservation->rooms->count() !== 1) {
                throw new RuntimeException('This editor currently supports one room-type line per reservation.');
            }

            $roomType = RoomType::query()
                ->whereKey($roomTypeId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            if ($roomType->max_adults !== null && $adults > ($roomType->max_adults * $quantity)) {
                throw new RuntimeException('The selected room quantity cannot accommodate this many adults.');
            }
            if ($roomType->max_children !== null && $children > ($roomType->max_children * $quantity)) {
                throw new RuntimeException('The selected room quantity cannot accommodate this many children.');
            }

            $availability = $this->availability->forRoomType(
                $roomTypeId,
                $checkIn,
                $checkOut,
                $reservation->id
            );

            if ($availability['available_rooms'] < $quantity) {
                throw new RuntimeException('Requested room inventory is not available for the modified stay.');
            }

            $this->pricing->quote($roomTypeId, $ratePlanId, $checkIn, $checkOut, $quantity);

            $reservationRoom = $reservation->rooms->first();
            $reservationRoom->update([
                'room_type_id' => $roomTypeId,
                'rate_plan_id' => $ratePlanId,
                'quantity' => $quantity,
            ]);

            $reservation->update([
                'check_in_date' => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'adults' => $adults,
                'children' => $children,
                'pricing_status' => 'pending',
            ]);

            $reservation = $this->pricing->priceReservation($reservation->fresh('rooms'));

            $payments = (float) Payment::query()
                ->where('reservation_id', $reservation->id)
                ->where('status', 'succeeded')
                ->sum('amount');
            $paymentIds = Payment::query()->where('reservation_id', $reservation->id)->pluck('id');
            $refunds = $paymentIds->isEmpty() ? 0.0 : (float) Refund::query()
                ->whereIn('payment_id', $paymentIds)
                ->where('status', 'succeeded')
                ->sum('amount');
            $net = $payments - $refunds;

            $reservation->update([
                'payment_status' => $net <= 0
                    ? 'unpaid'
                    : ($net + 0.009 >= (float) $reservation->total ? 'paid' : 'partially_paid'),
            ]);

            return $reservation->fresh(['rooms', 'guestLinks.guest']);
        }, 3);
    }

    public function cancel(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($reservation->status, ['pending', 'confirmed'], true)) {
                throw new RuntimeException('Only pending or confirmed reservations can be cancelled.');
            }

            $reservation->update(['status' => 'cancelled']);

            return $reservation->fresh();
        }, 3);
    }

    public function markNoShow(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->status !== 'confirmed') {
                throw new RuntimeException('Only confirmed reservations can be marked no-show.');
            }

            if (today()->lt($reservation->check_in_date)) {
                throw new RuntimeException('A reservation cannot be marked no-show before its check-in date.');
            }

            $reservation->update(['status' => 'no_show']);

            return $reservation->fresh();
        }, 3);
    }
}
