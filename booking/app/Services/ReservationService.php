<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationHold;
use App\Models\ReservationRoom;
use App\Models\RoomType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ReservationService
{
    public function __construct(private PricingService $pricing)
    {
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

            $guest = Guest::query()->create([
                'first_name' => $guestData['first_name'],
                'last_name' => $guestData['last_name'] ?? null,
                'phone' => $guestData['phone'],
                'email' => $guestData['email'] ?? null,
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
            $hold->update(['converted_reservation_id' => $reservation->id]);

            return $reservation->load(['rooms', 'guestLinks.guest']);
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
