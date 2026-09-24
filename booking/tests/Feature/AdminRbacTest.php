<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Payment;
use App\Models\RestaurantOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_admin_route_redirects_to_login(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_administrator_can_open_setup(): void
    {
        $admin = AdminUser::query()->create([
            'name'=>'Admin',
            'email'=>'admin@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'administrator',
            'is_active'=>true,
        ]);

        $this->withSession(['admin_user_id'=>$admin->id])
            ->get('/admin/setup')
            ->assertOk();
    }

    public function test_front_desk_cannot_open_administrator_setup(): void
    {
        $user = AdminUser::query()->create([
            'name'=>'Front Desk',
            'email'=>'frontdesk@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'front_desk',
            'is_active'=>true,
        ]);

        $this->withSession(['admin_user_id'=>$user->id])
            ->get('/admin/setup')
            ->assertForbidden();
    }

    public function test_disabled_admin_session_is_rejected(): void
    {
        $user = AdminUser::query()->create([
            'name'=>'Disabled',
            'email'=>'disabled@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'administrator',
            'is_active'=>false,
        ]);

        $this->withSession(['admin_user_id'=>$user->id])
            ->get('/admin')
            ->assertRedirect('/admin/login');
    }
    public function test_restaurant_role_cannot_post_hotel_reservation_payment(): void
    {
        $user = AdminUser::query()->create([
            'name'=>'Restaurant',
            'email'=>'restaurant@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'restaurant',
            'is_active'=>true,
        ]);

        $this->withSession(['admin_user_id'=>$user->id])
            ->post('/admin/payments', [
                'idempotency_key'=>'56565656-5656-4565-8565-565656565656',
                'target_type'=>'reservation',
                'target_id'=>1,
                'method'=>'cash',
                'amount'=>100,
            ])
            ->assertForbidden();
    }

    public function test_restaurant_role_cannot_refund_payments(): void
    {
        $user = AdminUser::query()->create([
            'name'=>'Restaurant',
            'email'=>'restaurant-refund@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'restaurant',
            'is_active'=>true,
        ]);
        $payment = Payment::query()->create([
            'idempotency_key'=>'11111111-1111-4111-8111-111111111111',
            'method'=>'cash',
            'status'=>'succeeded',
            'amount'=>100,
            'paid_at'=>now(),
        ]);

        $this->withSession(['admin_user_id'=>$user->id])
            ->post('/admin/payments/'.$payment->id.'/refund', ['amount'=>10])
            ->assertForbidden();
    }

    public function test_kitchen_role_cannot_create_restaurant_orders_or_serve_them(): void
    {
        $user = AdminUser::query()->create([
            'name'=>'Kitchen',
            'email'=>'kitchen@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'kitchen',
            'is_active'=>true,
        ]);
        $order = RestaurantOrder::query()->create([
            'order_number'=>'RO-RBAC-1',
            'order_type'=>'takeaway',
            'status'=>'ready',
            'payment_status'=>'unpaid',
            'subtotal'=>100,
            'tax'=>0,
            'total'=>100,
        ]);

        $this->withSession(['admin_user_id'=>$user->id])
            ->post('/admin/restaurant/orders', [])
            ->assertForbidden();

        $this->withSession(['admin_user_id'=>$user->id])
            ->post('/admin/restaurant/orders/'.$order->id.'/status', ['status'=>'served'])
            ->assertForbidden();
    }

    public function test_kitchen_view_hides_order_creation_and_table_controls(): void
    {
        $user = AdminUser::query()->create([
            'name'=>'Kitchen View',
            'email'=>'kitchen-view@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'kitchen',
            'is_active'=>true,
        ]);

        $this->withSession(['admin_user_id'=>$user->id])
            ->get('/admin/restaurant')
            ->assertOk()
            ->assertSee('Orders')
            ->assertDontSee('New order')
            ->assertDontSee('Restaurant tables');
    }

    public function test_restaurant_payment_console_hides_hotel_balances_and_refund_capability(): void
    {
        $user = AdminUser::query()->create([
            'name'=>'Restaurant Payments',
            'email'=>'restaurant-payments@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'restaurant',
            'is_active'=>true,
        ]);

        $response = $this->withSession(['admin_user_id'=>$user->id])
            ->get('/admin/payments');

        $response->assertOk();
        $response->assertViewHas('canRefund', false);
        $response->assertViewHas('reservations', fn ($rows) => $rows->isEmpty());
        $response->assertViewHas('folios', fn ($rows) => $rows->isEmpty());
    }

}
