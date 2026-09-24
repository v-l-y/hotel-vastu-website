<?php

namespace Tests\Feature;

use App\Models\Folio;
use App\Models\Reservation;
use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RoomType;
use App\Models\Stay;
use App\Services\RestaurantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_service_posts_to_folio_only_when_served(): void
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

        $service = app(RestaurantService::class);
        $order = $service->createOrder([
            'order_type'=>'room_service','folio_id'=>$folio->id,
            'items'=>[['menu_item_id'=>$item->id,'quantity'=>2]],
        ]);

        $this->assertDatabaseMissing('folio_charges', ['source_key'=>'restaurant-order:'.$order->id]);

        $service->changeStatus($order, 'preparing');
        $service->changeStatus($order->fresh(), 'ready');
        $service->changeStatus($order->fresh(), 'served');

        $this->assertDatabaseHas('folio_charges', [
            'source_key'=>'restaurant-order:'.$order->id,
            'amount'=>1000,
        ]);
        $this->assertSame('charged_to_room', $order->fresh()->payment_status);
    }
}
