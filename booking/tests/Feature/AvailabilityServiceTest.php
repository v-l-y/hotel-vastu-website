<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationHold;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_reservations_and_active_holds_reduce_availability(): void
    {
        $type = RoomType::query()->create(['code' => 'classic', 'name' => 'Classic Room', 'is_active' => true]);
        Room::query()->create(['room_type_id' => $type->id, 'number' => '101']);
        Room::query()->create(['room_type_id' => $type->id, 'number' => '102']);
        Room::query()->create(['room_type_id' => $type->id, 'number' => '103']);

        $reservation = Reservation::query()->create([
            'booking_number' => 'HV-TEST-1',
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12',
            'status' => 'confirmed',
        ]);

        ReservationRoom::query()->create([
            'reservation_id' => $reservation->id,
            'room_type_id' => $type->id,
            'quantity' => 1,
        ]);

        ReservationHold::query()->create([
            'token' => '11111111-1111-4111-8111-111111111111',
            'room_type_id' => $type->id,
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12',
            'quantity' => 1,
            'expires_at' => now()->addMinutes(10),
        ]);

        $result = app(AvailabilityService::class)->forRoomType(
            $type->id,
            CarbonImmutable::parse('2026-10-10'),
            CarbonImmutable::parse('2026-10-12')
        );

        $this->assertSame(3, $result['total_rooms']);
        $this->assertSame(1, $result['reserved_rooms']);
        $this->assertSame(1, $result['held_rooms']);
        $this->assertSame(1, $result['available_rooms']);
    }

    public function test_checkout_day_does_not_block_a_new_checkin(): void
    {
        $type = RoomType::query()->create(['code' => 'premium', 'name' => 'Premium Room', 'is_active' => true]);
        Room::query()->create(['room_type_id' => $type->id, 'number' => '201']);

        $reservation = Reservation::query()->create([
            'booking_number' => 'HV-TEST-2',
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12',
            'status' => 'confirmed',
        ]);

        ReservationRoom::query()->create([
            'reservation_id' => $reservation->id,
            'room_type_id' => $type->id,
            'quantity' => 1,
        ]);

        $result = app(AvailabilityService::class)->forRoomType(
            $type->id,
            CarbonImmutable::parse('2026-10-12'),
            CarbonImmutable::parse('2026-10-13')
        );

        $this->assertSame(1, $result['available_rooms']);
        $this->assertSame(0, $result['reserved_rooms']);
    }
}
