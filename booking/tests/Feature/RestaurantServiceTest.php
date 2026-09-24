<?php

namespace Tests\Feature;

use App\Models\Folio;
use App\Models\Reservation;
use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\TaxRule;
use App\Services\RestaurantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_service_generates_kot_and_posts_to_folio_only_when_served(): void
    {
        RoomType::query()->create(['code'=>'classic','name'=>'Classic Room','is_active'=>true]);
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-RS-1','public_token'=>'55555555-5555-4555-8555-555555555555',
            'check_in_date'=>today(),'check_out_date'=>today()->addDay(),
            'status'=>'checked_in','pricing_status'=>'priced','payment_status'=>'unpaid',
        ]);
        $stay = Stay::query()->create(['reservation_id'=>$reservation->id,'status'=>'checked_in','checked_in_at'=>now()]);
        $folio = Folio::query()->create(['stay_id'=>$stay->id,'reservation_id'=>$reservation->id,'status'=>'open']);
        $category = RestaurantCategory::query()->create(['name'=>'Main','is_active'=>true]);
        $item = RestaurantMenuItem::query()->create([
            'restaurant_category_id'=>$category->id,'name'=>'Test Meal','price'=>500,'is_active'=>true,
        ]);
        TaxRule::query()->create([
            'name'=>'Restaurant tax',
            'applies_to'=>'restaurant',
            'rate_percent'=>5,
            'is_active'=>true,
        ]);

        $service = app(RestaurantService::class);
        $order = $service->createOrder([
            'order_type'=>'room_service','folio_id'=>$folio->id,
            'items'=>[['menu_item_id'=>$item->id,'quantity'=>2]],
        ]);

        $this->assertDatabaseHas('kitchen_tickets', [
            'restaurant_order_id'=>$order->id,
            'status'=>'pending',
        ]);
        $this->assertDatabaseHas('kitchen_ticket_items', [
            'kitchen_ticket_id'=>$order->kitchenTicket->id,
        ]);
        $this->assertSame('1050.00', $order->total);
        $this->assertDatabaseMissing('folio_charges', ['source_key'=>'restaurant-order:'.$order->id]);

        $service->changeStatus($order, 'preparing');
        $this->assertSame('preparing', $order->fresh()->kitchenTicket->status);

        $service->changeStatus($order->fresh(), 'ready');
        $this->assertSame('ready', $order->fresh()->kitchenTicket->status);

        $service->changeStatus($order->fresh(), 'served');

        $this->assertDatabaseHas('folio_charges', [
            'source_key'=>'restaurant-order:'.$order->id,
            'subtotal'=>1000,
            'tax'=>50,
            'amount'=>1050,
        ]);
        $this->assertSame('served', $order->fresh()->kitchenTicket->status);
        $this->assertSame('charged_to_room', $order->fresh()->payment_status);
    }
}
