<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\KitchenTicket;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationNightRate;
use App\Models\ReservationRoom;
use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_use_saleable_room_nights_and_actual_restaurant_served_time(): void
    {
        $admin = AdminUser::query()->create([
            'name'=>'Accounts',
            'email'=>'accounts-report@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'accounts',
            'is_active'=>true,
        ]);

        $type = RoomType::query()->create([
            'code'=>'report-room',
            'name'=>'Report Room',
            'base_rate'=>1000,
            'is_active'=>true,
        ]);
        $roomA = Room::query()->create([
            'room_type_id'=>$type->id,
            'number'=>'R-101',
            'status'=>'active',
            'housekeeping_status'=>'clean',
        ]);
        $roomB = Room::query()->create([
            'room_type_id'=>$type->id,
            'number'=>'R-102',
            'status'=>'active',
            'housekeeping_status'=>'clean',
        ]);
        RoomBlock::query()->create([
            'room_id'=>$roomB->id,
            'starts_on'=>today()->toDateString(),
            'ends_on'=>today()->addDay()->toDateString(),
            'reason'=>'Maintenance',
            'status'=>'active',
        ]);

        $plan = RatePlan::query()->create([
            'code'=>'report-plan',
            'name'=>'Report Plan',
            'is_active'=>true,
        ]);
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-REPORT-1',
            'check_in_date'=>today(),
            'check_out_date'=>today()->addDay(),
            'status'=>'confirmed',
            'pricing_status'=>'priced',
            'payment_status'=>'unpaid',
            'subtotal'=>1000,
            'tax'=>0,
            'total'=>1000,
        ]);
        $reservationRoom = ReservationRoom::query()->create([
            'reservation_id'=>$reservation->id,
            'room_type_id'=>$type->id,
            'rate_plan_id'=>$plan->id,
            'quantity'=>1,
            'nightly_rate'=>1000,
        ]);
        ReservationNightRate::query()->create([
            'reservation_id'=>$reservation->id,
            'reservation_room_id'=>$reservationRoom->id,
            'room_type_id'=>$type->id,
            'rate_plan_id'=>$plan->id,
            'stay_date'=>today()->toDateString(),
            'quantity'=>1,
            'unit_rate'=>1000,
            'line_total'=>1000,
            'tax_rate'=>0,
            'tax_amount'=>0,
            'gross_total'=>1000,
        ]);

        $category = RestaurantCategory::query()->create([
            'name'=>'Report Food',
            'is_active'=>true,
        ]);
        $menuItem = RestaurantMenuItem::query()->create([
            'restaurant_category_id'=>$category->id,
            'name'=>'Report Meal',
            'price'=>500,
            'is_active'=>true,
        ]);

        $order = RestaurantOrder::query()->create([
            'order_number'=>'RO-REPORT-1',
            'order_type'=>'takeaway',
            'status'=>'served',
            'payment_status'=>'paid',
            'subtotal'=>500,
            'tax'=>0,
            'total'=>500,
        ]);
        RestaurantOrderItem::query()->create([
            'restaurant_order_id'=>$order->id,
            'restaurant_menu_item_id'=>$menuItem->id,
            'item_name'=>$menuItem->name,
            'quantity'=>1,
            'unit_price'=>500,
            'line_total'=>500,
        ]);
        KitchenTicket::query()->create([
            'ticket_number'=>'KOT-REPORT-1',
            'restaurant_order_id'=>$order->id,
            'status'=>'served',
            'served_at'=>now(),
        ]);
        RestaurantOrder::query()->whereKey($order->id)->update([
            'updated_at'=>now()->addDays(5),
        ]);

        $response = $this->withSession(['admin_user_id'=>$admin->id])
            ->get('/admin/reports?from='.today()->toDateString().'&to='.today()->toDateString());

        $response->assertOk();
        $response->assertViewHas('bookedRoomNights', 1);
        $response->assertViewHas('occupancyPercent', 100.0);
        $response->assertViewHas('restaurantSales', 500.0);
        $response->assertViewHas('topRestaurantItems', function ($items) {
            return $items->count() === 1
                && $items->first()->item_name === 'Report Meal'
                && (int) $items->first()->quantity_sold === 1;
        });

        $this->assertNotNull($roomA);
    }
}
