<?php

namespace Tests\Feature;

use App\Models\Folio;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationRoom;
use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayRoom;
use App\Services\FrontDeskService;
use App\Services\PaymentService;
use App\Services\RestaurantService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OperationsHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $suffix): array
    {
        $type = RoomType::query()->create([
            'code' => 'ops-'.$suffix,
            'name' => 'Classic Room',
            'base_rate' => 1000,
            'is_active' => true,
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'ops-plan-'.$suffix,
            'name' => 'Standard',
            'is_active' => true,
        ]);
        $roomA = Room::query()->create([
            'room_type_id' => $type->id,
            'number' => 'A'.$suffix,
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        $roomB = Room::query()->create([
            'room_type_id' => $type->id,
            'number' => 'B'.$suffix,
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        $guest = Guest::query()->create([
            'first_name' => 'Guest',
            'phone' => '90000000'.$suffix,
        ]);
        $reservation = Reservation::query()->create([
            'booking_number' => 'HV-OPS-'.$suffix,
            'public_token' => sprintf('bbbbbbbb-bbbb-4bbb-8bbb-%012d', (int) $suffix),
            'check_in_date' => today(),
            'check_out_date' => today()->addDay(),
            'adults' => 2,
            'status' => 'confirmed',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
        ]);
        ReservationRoom::query()->create([
            'reservation_id' => $reservation->id,
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'quantity' => 1,
            'nightly_rate' => 1000,
        ]);
        ReservationGuest::query()->create([
            'reservation_id' => $reservation->id,
            'guest_id' => $guest->id,
            'role' => 'primary',
        ]);

        return [$reservation, $roomA, $roomB];
    }

    public function test_room_transfer_marks_old_room_dirty_and_assigns_ready_target(): void
    {
        [$reservation, $roomA, $roomB] = $this->fixture('11');
        $service = app(FrontDeskService::class);
        $stay = $service->checkIn($reservation, [$roomA->id]);

        $service->transferRoom($stay, $roomA->id, $roomB->id);

        $this->assertSame('dirty', $roomA->fresh()->housekeeping_status);
        $this->assertDatabaseHas('stay_rooms', [
            'stay_id' => $stay->id,
            'room_id' => $roomB->id,
            'released_at' => null,
        ]);
        $this->assertNotNull(
            StayRoom::query()->where('stay_id', $stay->id)->where('room_id', $roomA->id)->value('released_at')
        );
    }

    public function test_stay_extension_posts_extra_room_charge_and_nightly_snapshot(): void
    {
        [$reservation, $roomA] = $this->fixture('12');
        $service = app(FrontDeskService::class);
        $stay = $service->checkIn($reservation, [$roomA->id]);

        $service->extendStay($stay, CarbonImmutable::parse(today()->addDays(2)));

        $this->assertSame(today()->addDays(2)->toDateString(), $reservation->fresh()->check_out_date->toDateString());
        $this->assertSame('2000.00', $reservation->fresh()->total);
        $this->assertDatabaseHas('folio_charges', [
            'folio_id' => $stay->folio->id,
            'category' => 'room',
            'amount' => 1000,
            'source_key' => 'stay-extension:'.$stay->id.':'.today()->addDays(2)->toDateString(),
        ]);
        $this->assertDatabaseHas('reservation_night_rates', [
            'reservation_id' => $reservation->id,
            'stay_date' => today()->addDay()->toDateString().' 00:00:00',
            'gross_total' => 1000,
        ]);
    }

    public function test_pending_room_service_blocks_checkout_even_when_current_folio_balance_is_zero(): void
    {
        [$reservation, $roomA] = $this->fixture('13');
        $frontDesk = app(FrontDeskService::class);
        $stay = $frontDesk->checkIn($reservation, [$roomA->id]);

        app(PaymentService::class)->record([
            'idempotency_key' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'folio_id' => $stay->folio->id,
            'method' => 'cash',
            'amount' => 1000,
        ]);

        $category = RestaurantCategory::query()->create(['name' => 'Food', 'is_active' => true]);
        $item = RestaurantMenuItem::query()->create([
            'restaurant_category_id' => $category->id,
            'name' => 'Meal',
            'price' => 300,
            'is_active' => true,
        ]);
        app(RestaurantService::class)->createOrder([
            'order_type' => 'room_service',
            'folio_id' => $stay->folio->id,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pending room-service');

        $frontDesk->checkOut($stay);
    }

    public function test_dine_in_multi_item_order_occupies_then_releases_table(): void
    {
        $category = RestaurantCategory::query()->create(['name' => 'Food', 'is_active' => true]);
        $first = RestaurantMenuItem::query()->create([
            'restaurant_category_id' => $category->id,
            'name' => 'Meal',
            'price' => 300,
            'is_active' => true,
        ]);
        $second = RestaurantMenuItem::query()->create([
            'restaurant_category_id' => $category->id,
            'name' => 'Tea',
            'price' => 50,
            'is_active' => true,
        ]);
        $table = \App\Models\RestaurantTable::query()->create([
            'code' => 'T1',
            'name' => 'Table 1',
            'status' => 'available',
            'is_active' => true,
        ]);

        $service = app(RestaurantService::class);
        $order = $service->createOrder([
            'order_type' => 'dine_in',
            'restaurant_table_id' => $table->id,
            'items' => [
                ['menu_item_id' => $first->id, 'quantity' => 2],
                ['menu_item_id' => $second->id, 'quantity' => 3],
            ],
        ]);

        $this->assertSame('occupied', $table->fresh()->status);
        $this->assertCount(2, $order->items);
        $this->assertSame('750.00', $order->total);

        $service->changeStatus($order, 'preparing');
        $service->changeStatus($order->fresh(), 'ready');
        $service->changeStatus($order->fresh(), 'served');

        $this->assertSame('available', $table->fresh()->status);
    }
}
