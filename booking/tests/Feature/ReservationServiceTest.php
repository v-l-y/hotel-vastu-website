<?php

namespace Tests\Feature;

use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationHold;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_priced_hold_can_be_converted_into_a_confirmed_reservation_once(): void
    {
        $type = RoomType::query()->create([
            'code' => 'classic',
            'name' => 'Classic Room',
            'base_rate' => 2000,
            'is_active' => true,
        ]);
        $plan = RatePlan::query()->create(['code' => 'standard', 'name' => 'Standard', 'is_active' => true]);
        Room::query()->create(['room_type_id' => $type->id, 'number' => '101']);

        $hold = ReservationHold::query()->create([
            'token' => '22222222-2222-4222-8222-222222222222',
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12',
            'quantity' => 1,
            'adults' => 2,
            'children' => 1,
            'expires_at' => now()->addMinutes(10),
        ]);

        $reservation = app(ReservationService::class)->confirmHold($hold->token, [
            'first_name' => 'Test',
            'last_name' => 'Guest',
            'phone' => '+91 90000 00000',
            'email' => 'guest@example.com',
        ]);

        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame('priced', $reservation->pricing_status);
        $this->assertSame('4000.00', $reservation->total);
        $this->assertNotNull($reservation->public_token);
        $this->assertSame(2, $reservation->adults);
        $this->assertDatabaseHas('reservation_night_rates', ['reservation_id' => $reservation->id]);
        $this->assertDatabaseHas('reservation_holds', [
            'id' => $hold->id,
            'converted_reservation_id' => $reservation->id,
        ]);
    }

    public function test_expired_hold_cannot_create_a_reservation(): void
    {
        $type = RoomType::query()->create([
            'code' => 'premium',
            'name' => 'Premium Room',
            'base_rate' => 3000,
            'is_active' => true,
        ]);
        $plan = RatePlan::query()->create(['code' => 'standard', 'name' => 'Standard', 'is_active' => true]);

        $hold = ReservationHold::query()->create([
            'token' => '33333333-3333-4333-8333-333333333333',
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12',
            'quantity' => 1,
            'adults' => 1,
            'children' => 0,
            'expires_at' => now()->subMinute(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expired');

        try {
            app(ReservationService::class)->confirmHold($hold->token, [
                'first_name' => 'Expired',
                'phone' => '+91 90000 00000',
            ]);
        } finally {
            $this->assertSame(0, Reservation::query()->count());
        }
    }
}
