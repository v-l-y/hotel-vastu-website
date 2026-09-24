<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationFeedback;
use App\Models\ReservationGuest;
use App\Models\ReservationHold;
use App\Models\ReservationRoom;
use App\Models\RoomType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ReservationService
{
    public function __construct(
        private PricingService $pricing,
        private AvailabilityService $availability,
        private PromotionService $promotions
    ) {
    }

    public function confirmHold(string $token, array $guestData): Reservation
    {
        return DB::transaction(function () use ($token, $guestData) {
            $snapshot = ReservationHold::query()
                ->where('token', $token)
                ->firstOrFail();

            RoomType::query()
                ->whereKey($snapshot->room_type_id)
                ->lockForUpdate()
                ->firstOrFail();

            $hold = ReservationHold::query()
                ->whereKey($snapshot->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($hold->converted_reservation_id !== null) {
                throw new RuntimeException('This room hold has already been used.');
            }

            if ($hold->expires_at->isPast()) {
                throw new RuntimeException('This room hold has expired. Please search availability again.');
            }

            if ($hold->rate_plan_id === null) {
                throw new RuntimeException('This room hold does not have a valid rate plan.');
            }

            $baseQuote = $this->pricing->quote(
                $hold->room_type_id,
                $hold->rate_plan_id,
                \Carbon\CarbonImmutable::parse($hold->check_in_date),
                \Carbon\CarbonImmutable::parse($hold->check_out_date),
                $hold->quantity
            );
            $promotion = $this->promotions->consume(
                $guestData['promo_code'] ?? null,
                (float) $baseQuote['subtotal']
            );

            $guest = Guest::query()->create([
                'first_name' => $guestData['first_name'],
                'last_name' => $guestData['last_name'] ?? null,
                'phone' => $guestData['phone'],
                'email' => $guestData['email'] ?? null,
                'gstin' => isset($guestData['gstin']) ? strtoupper(trim($guestData['gstin'])) : null,
                'billing_address' => $guestData['billing_address'] ?? null,
                'billing_state' => $guestData['billing_state'] ?? null,
                'billing_state_code' => $guestData['billing_state_code'] ?? null,
            ]);

            $reservation = Reservation::query()->create([
                'booking_number' => $this->nextBookingNumber(),
                'public_token' => (string) Str::uuid(),
                'check_in_date' => $hold->check_in_date,
                'check_out_date' => $hold->check_out_date,
                'adults' => $hold->adults,
                'children' => $hold->children,
                'status' => 'confirmed',
                'source' => 'website',
                'special_request' => $guestData['special_request'] ?? null,
                'payment_status' => 'unpaid',
                'pricing_status' => 'pending',
                ...($promotion ?? []),
            ]);

            ReservationRoom::query()->create([
                'reservation_id' => $reservation->id,
                'room_type_id' => $hold->room_type_id,
                'rate_plan_id' => $hold->rate_plan_id,
                'quantity' => $hold->quantity,
                'nightly_rate' => null,
            ]);

            ReservationGuest::query()->create([
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'role' => 'primary',
            ]);

            $reservation = $this->pricing->priceReservation($reservation);
            if ((float) $reservation->total <= 0.009) {
                $reservation->update(['payment_status' => 'paid']);
                $reservation = $reservation->fresh(['rooms']);
            }
            $hold->update(['converted_reservation_id' => $reservation->id]);

            ReservationFeedback::query()->create([
                'reservation_id' => $reservation->id,
                'token' => (string) Str::uuid(),
            ]);

            return $reservation->load(['rooms', 'guestLinks.guest', 'feedback']);
        }, 3);
    }

    public function createFrontDeskBooking(array $data): Reservation
    {
        return DB::transaction(function () use ($data) {
            $checkIn = \Carbon\CarbonImmutable::parse($data['check_in']);
            $checkOut = \Carbon\CarbonImmutable::parse($data['check_out']);
            $quantity = (int) $data['rooms'];
            $adults = (int) $data['adults'];
            $children = (int) ($data['children'] ?? 0);
            $roomTypeId = (int) $data['room_type_id'];
            $ratePlanId = (int) $data['rate_plan_id'];

            $roomType = RoomType::query()
                ->whereKey($roomTypeId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            \App\Models\RatePlan::query()
                ->whereKey($ratePlanId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            if ($checkIn->isBefore(today()) || $checkOut->lessThanOrEqualTo($checkIn)) {
                throw new RuntimeException('Choose a valid current or future stay window.');
            }

            if ($roomType->max_adults !== null && $adults > ($roomType->max_adults * $quantity)) {
                throw new RuntimeException('The selected room quantity cannot accommodate this many adults.');
            }

            if ($roomType->max_children !== null && $children > ($roomType->max_children * $quantity)) {
                throw new RuntimeException('The selected room quantity cannot accommodate this many children.');
            }

            $availability = $this->availability->forRoomType(
                $roomTypeId,
                $checkIn,
                $checkOut
            );

            if ($availability['available_rooms'] < $quantity) {
                throw new RuntimeException('Requested room inventory is no longer available.');
            }

            $baseQuote = $this->pricing->quote(
                $roomTypeId,
                $ratePlanId,
                $checkIn,
                $checkOut,
                $quantity
            );
            $promotion = $this->promotions->consume(
                $data['promo_code'] ?? null,
                (float) $baseQuote['subtotal']
            );

            $guest = Guest::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'gstin' => isset($data['gstin']) ? strtoupper(trim($data['gstin'])) : null,
                'billing_address' => $data['billing_address'] ?? null,
                'billing_state' => $data['billing_state'] ?? null,
                'billing_state_code' => $data['billing_state_code'] ?? null,
            ]);

            $reservation = Reservation::query()->create([
                'booking_number' => $this->nextBookingNumber(),
                'public_token' => (string) Str::uuid(),
                'check_in_date' => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'adults' => $adults,
                'children' => $children,
                'status' => 'confirmed',
                'source' => 'front_desk',
                'special_request' => $data['special_request'] ?? null,
                'payment_status' => 'unpaid',
                'pricing_status' => 'pending',
                ...($promotion ?? []),
            ]);

            ReservationRoom::query()->create([
                'reservation_id' => $reservation->id,
                'room_type_id' => $roomTypeId,
                'rate_plan_id' => $ratePlanId,
                'quantity' => $quantity,
                'nightly_rate' => null,
            ]);

            ReservationGuest::query()->create([
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'role' => 'primary',
            ]);

            $reservation = $this->pricing->priceReservation($reservation);
            if ((float) $reservation->total <= 0.009) {
                $reservation->update(['payment_status' => 'paid']);
                $reservation = $reservation->fresh(['rooms']);
            }

            ReservationFeedback::query()->create([
                'reservation_id' => $reservation->id,
                'token' => (string) Str::uuid(),
            ]);

            return $reservation->load(['rooms', 'guestLinks.guest', 'feedback']);
        }, 3);
    }

    private function nextBookingNumber(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $number = 'HV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));

            if (! Reservation::query()->where('booking_number', $number)->exists()) {
                return $number;
            }
        }

        throw new RuntimeException('Could not generate a unique booking number.');
    }
}
