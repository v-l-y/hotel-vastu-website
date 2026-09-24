<?php

namespace Tests\Feature;

use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\PaymentService;
use App\Services\ReservationLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationModificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_reservation_can_modify_over_its_existing_inventory_and_is_repriced(): void
    {
        $type = RoomType::query()->create([
            'code' => 'edit-classic',
            'name' => 'Classic Room',
            'base_rate' => 1000,
            'is_active' => true,
        ]);
        Room::query()->create([
            'room_type_id' => $type->id,
            'number' => 'E101',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'edit-standard',
            'name' => 'Standard',
            'is_active' => true,
        ]);

        $reservation = Reservation::query()->create([
            'booking_number' => 'HV-EDIT-1',
            'public_token' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'check_in_date' => today()->addDays(2),
            'check_out_date' => today()->addDays(3),
            'adults' => 2,
            'status' => 'confirmed',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => 1000,
            'total' => 1000,
        ]);
        ReservationRoom::query()->create([
            'reservation_id' => $reservation->id,
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'quantity' => 1,
            'nightly_rate' => 1000,
        ]);

        app(ReservationLifecycleService::class)->modifyConfirmed(
            $reservation,
            CarbonImmutable::parse(today()->addDays(2)),
            CarbonImmutable::parse(today()->addDays(4)),
            $type->id,
            $plan->id,
            1,
            2,
            0
        );

        $fresh = $reservation->fresh();
        $this->assertSame(today()->addDays(4)->toDateString(), $fresh->check_out_date->toDateString());
        $this->assertSame('2000.00', $fresh->total);
        $this->assertSame('priced', $fresh->pricing_status);
        $this->assertSame(2, \App\Models\ReservationNightRate::query()->where('reservation_id', $reservation->id)->count());
    }
    public function test_repricing_below_existing_payment_marks_reservation_overpaid(): void
    {
        $originalType = RoomType::query()->create([
            'code' => 'edit-paid-original',
            'name' => 'Original Room',
            'base_rate' => 1000,
            'is_active' => true,
        ]);
        $lowerType = RoomType::query()->create([
            'code' => 'edit-paid-lower',
            'name' => 'Lower Room',
            'base_rate' => 800,
            'is_active' => true,
        ]);
        Room::query()->create([
            'room_type_id' => $originalType->id,
            'number' => 'E201',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        Room::query()->create([
            'room_type_id' => $lowerType->id,
            'number' => 'E202',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'edit-paid-plan',
            'name' => 'Paid Plan',
            'is_active' => true,
        ]);

        $reservation = Reservation::query()->create([
            'booking_number' => 'HV-EDIT-PAID',
            'public_token' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'check_in_date' => today()->addDays(2),
            'check_out_date' => today()->addDays(3),
            'adults' => 1,
            'status' => 'confirmed',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
        ]);
        ReservationRoom::query()->create([
            'reservation_id' => $reservation->id,
            'room_type_id' => $originalType->id,
            'rate_plan_id' => $plan->id,
            'quantity' => 1,
            'nightly_rate' => 1000,
        ]);

        app(PaymentService::class)->record([
            'idempotency_key' => 'abababab-abab-4bab-8bab-abababababab',
            'reservation_id' => $reservation->id,
            'method' => 'cash',
            'amount' => 1000,
        ]);

        $updated = app(ReservationLifecycleService::class)->modifyConfirmed(
            $reservation->fresh(),
            CarbonImmutable::parse(today()->addDays(2)),
            CarbonImmutable::parse(today()->addDays(3)),
            $lowerType->id,
            $plan->id,
            1,
            1,
            0
        );

        $this->assertSame('800.00', $updated->total);
        $this->assertSame('overpaid', $updated->payment_status);
    }

}
