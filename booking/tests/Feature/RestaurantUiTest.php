<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\KitchenTicket;
use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_sees_only_active_kots_with_destination_and_special_notes_oldest_first(): void
    {
        $admin = AdminUser::query()->create([
            'name'=>'Kitchen',
            'email'=>'kitchen-kot@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'kitchen',
            'is_active'=>true,
        ]);

        $category = RestaurantCategory::query()->create(['name'=>'Main','is_active'=>true]);
        $menuItem = RestaurantMenuItem::query()->create([
            'restaurant_category_id'=>$category->id,
            'name'=>'Paneer Meal',
            'price'=>300,
            'is_active'=>true,
        ]);
        $table = RestaurantTable::query()->create([
            'code'=>'T7',
            'name'=>'Table 7',
            'capacity'=>4,
            'status'=>'occupied',
            'is_active'=>true,
        ]);

        $old = $this->order('RO-ACTIVE-OLD', 'accepted', $table->id);
        $old->forceFill(['created_at'=>now()->subMinutes(12)])->save();
        $this->ticket($old, 'KOT-ACTIVE-OLD', 'pending');
        $this->item($old, $menuItem, 'No onion');

        $new = $this->order('RO-ACTIVE-NEW', 'preparing', $table->id);
        $new->forceFill(['created_at'=>now()->subMinutes(3)])->save();
        $this->ticket($new, 'KOT-ACTIVE-NEW', 'preparing');
        $this->item($new, $menuItem, 'Less spicy');

        $served = $this->order('RO-SERVED-HIDDEN', 'served', $table->id);
        $this->ticket($served, 'KOT-SERVED-HIDDEN', 'served');
        $this->item($served, $menuItem, 'Completed note');

        $response = $this->withSession([
            'admin_user_id'=>$admin->id,
            'admin_session_version'=>$admin->session_version,
        ])->get('/admin/restaurant');

        $response->assertOk()
            ->assertSee('Kitchen KOT')
            ->assertSee('Table 7')
            ->assertSee('No onion')
            ->assertSee('Less spicy')
            ->assertSeeInOrder(['KOT-ACTIVE-OLD', 'KOT-ACTIVE-NEW'])
            ->assertDontSee('KOT-SERVED-HIDDEN')
            ->assertDontSee('New order');
    }

    private function order(string $number, string $status, int $tableId): RestaurantOrder
    {
        return RestaurantOrder::query()->create([
            'order_number'=>$number,
            'order_type'=>'dine_in',
            'restaurant_table_id'=>$tableId,
            'status'=>$status,
            'payment_status'=>'unpaid',
            'subtotal'=>300,
            'tax'=>0,
            'total'=>300,
        ]);
    }

    private function ticket(RestaurantOrder $order, string $number, string $status): void
    {
        KitchenTicket::query()->create([
            'ticket_number'=>$number,
            'restaurant_order_id'=>$order->id,
            'status'=>$status,
        ]);
    }

    private function item(RestaurantOrder $order, RestaurantMenuItem $menuItem, string $note): void
    {
        RestaurantOrderItem::query()->create([
            'restaurant_order_id'=>$order->id,
            'restaurant_menu_item_id'=>$menuItem->id,
            'item_name'=>$menuItem->name,
            'quantity'=>1,
            'unit_price'=>$menuItem->price,
            'line_total'=>$menuItem->price,
            'note'=>$note,
        ]);
    }
}
