<?php

namespace Tests\Feature;

use App\Models\Folio;
use App\Models\Guest;
use App\Models\PaymentGatewayOrder;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationRoom;
use App\Models\ReservationNightRate;
use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Services\FrontDeskService;
use App\Services\PaymentService;
use App\Services\ReservationLifecycleService;
use App\Services\RestaurantService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OperationsHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function checkedInFixture(): array
    {
        $type = RoomType::query()->create([
            'code' => 'ops-classic',
            'name' => 'Classic Room',
            'base_rate' => 2000,
            'max_adults' => 2,
            'max_children' => 2,
            'is_active' => true,
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'ops-standard',
            'name' => 'Standard',
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'room_type_id' => $type->id,
            'number' => '201',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        $guest = Guest::query()->create([
            'first_name' => 'Ops',
            'phone' => '9000000021',
        ]);
        $reservation = Reservation::query()->create([
            'booking_number' => 'HV-OPS-1',
            'public_token' => (string) Str::uuid(),
            'check_in_date' => today(),
            'check_out_date' => today()->addDay(),
            'adults' => 2,
            'children' => 0,
            'status' => 'confirmed',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => 2000,
            'tax' => 0,
            'total' => 2000,
        ]);
        ReservationRoom::query()->create([
            'reservation_id' => $reservation->id,
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'quantity' => 1,
            'nightly_rate' => 2000,
        ]);
        ReservationGuest::query()->create([
            'reservation_id' => $reservation->id,
            'guest_id' => $guest->id,
            'role' => 'primary',
        ]);

        $stay = app(FrontDeskService::class)->checkIn($reservation, [$room->id]);

        return [$type, $plan, $room, $reservation->fresh(), $stay];
    }

    public function test_out_of_order_room_is_removed_from_saleable_inventory(): void
    {
        $type = RoomType::query()->create([
            'code' => 'inventory',
            'name' => 'Inventory Room',
            'base_rate' => 1000,
            'is_active' => true,
        ]);
        Room::query()->create([
            'room_type_id' => $type->id,
            'number' => '301',
            'status' => 'active',
            'housekeeping_status' => 'out_of_order',
        ]);
        Room::query()->create([
            'room_type_id' => $type->id,
            'number' => '302',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);

        $result = app(AvailabilityService::class)->forRoomType(
            $type->id,
            CarbonImmutable::parse(today()->addDay()),
            CarbonImmutable::parse(today()->addDays(2))
        );

        $this->assertSame(1, $result['total_rooms']);
        $this->assertSame(1, $result['available_rooms']);
    }

    public function test_room_transfer_marks_released_room_dirty_and_assigns_ready_same_type_room(): void
    {
        [$type, , $room, , $stay] = $this->checkedInFixture();
        $target = Room::query()->create([
            'room_type_id' => $type->id,
            'number' => '202',
            'status' => 'active',
            'housekeeping_status' => 'inspected',
        ]);

        app(FrontDeskService::class)->transferRoom($stay, $room->id, $target->id);

        $this->assertSame('dirty', $room->fresh()->housekeeping_status);
        $this->assertDatabaseHas('stay_rooms', [
            'stay_id' => $stay->id,
            'room_id' => $target->id,
            'released_at' => null,
        ]);
    }

    public function test_pending_room_service_blocks_checkout_even_when_folio_balance_is_zero(): void
    {
        [, , , , $stay] = $this->checkedInFixture();

        app(PaymentService::class)->record([
            'idempotency_key' => (string) Str::uuid(),
            'folio_id' => $stay->folio->id,
            'method' => 'cash',
            'amount' => 2000,
        ]);

        $category = RestaurantCategory::query()->create([
            'name' => 'Meals',
            'is_active' => true,
        ]);
        $item = RestaurantMenuItem::query()->create([
            'restaurant_category_id' => $category->id,
            'name' => 'Dinner',
            'price' => 400,
            'is_active' => true,
        ]);

        app(RestaurantService::class)->createOrder([
            'order_type' => 'room_service',
            'folio_id' => $stay->folio->id,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('room-service');

        app(FrontDeskService::class)->checkOut($stay);
    }

    public function test_stay_extension_adds_new_night_snapshot_and_folio_charge(): void
    {
        [, , , $reservation, $stay] = $this->checkedInFixture();

        app(FrontDeskService::class)->extendStay($stay, CarbonImmutable::parse(today()->addDays(2)));

        $this->assertSame(today()->addDays(2)->toDateString(), $reservation->fresh()->check_out_date->toDateString());
        $night = ReservationNightRate::query()
            ->where('reservation_id', $reservation->id)
            ->whereDate('stay_date', today()->addDay()->toDateString())
            ->firstOrFail();
        $this->assertSame('2000.00', $night->line_total);
        $this->assertDatabaseHas('folio_charges', [
            'folio_id' => $stay->folio->id,
            'source_key' => 'stay-extension:'.$stay->id.':'.today()->addDays(2)->toDateString(),
            'amount' => 2000,
        ]);
    }

    public function test_confirmed_reservation_can_be_modified_repriced_and_stales_old_gateway_order(): void
    {
        $oldType = RoomType::query()->create([
            'code' => 'old-type',
            'name' => 'Old Room',
            'base_rate' => 1000,
            'max_adults' => 2,
            'max_children' => 1,
            'is_active' => true,
        ]);
        $newType = RoomType::query()->create([
            'code' => 'new-type',
            'name' => 'New Room',
            'base_rate' => 1500,
            'max_adults' => 3,
            'max_children' => 2,
            'is_active' => true,
        ]);
        Room::query()->create([
            'room_type_id' => $oldType->id,
            'number' => '401',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        Room::query()->create([
            'room_type_id' => $newType->id,
            'number' => '402',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'modify-plan',
            'name' => 'Modify Plan',
            'is_active' => true,
        ]);
        $reservation = Reservation::query()->create([
            'booking_number' => 'HV-MODIFY-1',
            'public_token' => (string) Str::uuid(),
            'check_in_date' => today()->addDay(),
            'check_out_date' => today()->addDays(2),
            'status' => 'confirmed',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
        ]);
        ReservationRoom::query()->create([
            'reservation_id' => $reservation->id,
            'room_type_id' => $oldType->id,
            'rate_plan_id' => $plan->id,
            'quantity' => 1,
            'nightly_rate' => 1000,
        ]);
        $gatewayOrder = PaymentGatewayOrder::query()->create([
            'provider' => 'razorpay',
            'reservation_id' => $reservation->id,
            'provider_order_id' => 'order_old_quote',
            'amount_subunits' => 100000,
            'currency' => 'INR',
            'status' => 'created',
        ]);

        $updated = app(ReservationLifecycleService::class)->modifyConfirmed(
            $reservation,
            CarbonImmutable::parse(today()->addDays(2)),
            CarbonImmutable::parse(today()->addDays(4)),
            $newType->id,
            $plan->id,
            1,
            2,
            1
        );

        $this->assertSame('3000.00', $updated->total);
        $this->assertSame($newType->id, $updated->rooms->first()->room_type_id);
        $this->assertSame(2, $updated->fresh()->nightRates()->count());
        $this->assertSame('stale', $gatewayOrder->fresh()->status);
    }
}
