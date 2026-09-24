<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RestaurantOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_is_scoped_for_each_role(): void
    {
        $matrix = [
            'administrator' => [
                'see' => ['Front Desk', 'Restaurant', 'Payments', 'Reports', 'Setup', 'Users & Audit', 'Security'],
                'hide' => [],
            ],
            'front_desk' => [
                'see' => ['Front Desk', 'Payments', 'Reports', 'Security'],
                'hide' => ['Restaurant', 'Setup', 'Users & Audit'],
            ],
            'restaurant' => [
                'see' => ['Restaurant', 'Payments', 'Security'],
                'hide' => ['Front Desk', 'Reports', 'Setup', 'Users & Audit'],
            ],
            'kitchen' => [
                'see' => ['Restaurant', 'Security'],
                'hide' => ['Front Desk', 'Payments', 'Reports', 'Setup', 'Users & Audit'],
            ],
            'accounts' => [
                'see' => ['Payments', 'Reports', 'Security'],
                'hide' => ['Front Desk', 'Restaurant', 'Setup', 'Users & Audit'],
            ],
        ];

        foreach ($matrix as $role => $expectations) {
            $admin = $this->admin($role, $role.'-nav@example.com');

            $response = $this->withSession($this->sessionFor($admin))->get('/admin');

            $response->assertOk();

            foreach ($expectations['see'] as $label) {
                $response->assertSee($label);
            }

            foreach ($expectations['hide'] as $label) {
                $response->assertDontSee($label);
            }
        }
    }

    public function test_dashboards_only_show_role_relevant_metrics(): void
    {
        $frontDesk = $this->admin('front_desk', 'frontdesk-dashboard@example.com');
        $this->withSession($this->sessionFor($frontDesk))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Confirmed arrivals')
            ->assertSee('In-house stays')
            ->assertSee('Open folios')
            ->assertDontSee('Active restaurant orders')
            ->assertDontSee('KOT queue');

        $restaurant = $this->admin('restaurant', 'restaurant-dashboard@example.com');
        $this->withSession($this->sessionFor($restaurant))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Active restaurant orders')
            ->assertSee('Unsettled restaurant bills')
            ->assertDontSee('Confirmed arrivals')
            ->assertDontSee('Open folios');

        $kitchen = $this->admin('kitchen', 'kitchen-dashboard@example.com');
        $this->withSession($this->sessionFor($kitchen))
            ->get('/admin')
            ->assertOk()
            ->assertSee('KOT queue')
            ->assertDontSee('Confirmed arrivals')
            ->assertDontSee('Successful payments');

        $accounts = $this->admin('accounts', 'accounts-dashboard@example.com');
        $this->withSession($this->sessionFor($accounts))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Successful payments')
            ->assertSee('Pending refunds')
            ->assertSee('Invoices issued')
            ->assertDontSee('Confirmed arrivals')
            ->assertDontSee('Active restaurant orders');
    }

    public function test_restaurant_payment_ui_hides_hotel_and_invoice_sections(): void
    {
        $restaurant = $this->admin('restaurant', 'restaurant-ui@example.com');

        $this->withSession($this->sessionFor($restaurant))
            ->get('/admin/payments')
            ->assertOk()
            ->assertSee('Restaurant balances')
            ->assertDontSee('Open hotel balances')
            ->assertDontSee('Pre-arrival reservation payments')
            ->assertDontSee('Recent invoices');
    }

    public function test_front_desk_payment_ui_is_hotel_only_and_invoices_are_discoverable(): void
    {
        $frontDesk = $this->admin('front_desk', 'frontdesk-ui@example.com');

        $this->withSession($this->sessionFor($frontDesk))
            ->get('/admin/payments')
            ->assertOk()
            ->assertSee('Open hotel balances')
            ->assertSee('Pre-arrival reservation payments')
            ->assertSee('Recent invoices')
            ->assertDontSee('Restaurant balances');
    }

    public function test_accounts_can_discover_invoices_from_payments_screen(): void
    {
        $accounts = $this->admin('accounts', 'accounts-invoice@example.com');

        $this->withSession($this->sessionFor($accounts))
            ->get('/admin/payments')
            ->assertOk()
            ->assertSee('Recent invoices')
            ->assertSee('No invoices available.');
    }

    public function test_front_desk_cannot_record_or_refund_direct_restaurant_payments(): void
    {
        $frontDesk = $this->admin('front_desk', 'frontdesk-boundary@example.com');
        $order = $this->restaurantOrder('RO-FD-RBAC-1');

        $this->withSession($this->sessionFor($frontDesk))
            ->post('/admin/payments', [
                'idempotency_key' => '31313131-3131-4313-8313-313131313131',
                'target_type' => 'restaurant_order',
                'target_id' => $order->id,
                'method' => 'cash',
                'amount' => 100,
            ])
            ->assertForbidden();

        $payment = Payment::query()->create([
            'idempotency_key' => '32323232-3232-4323-8323-323232323232',
            'restaurant_order_id' => $order->id,
            'method' => 'cash',
            'status' => 'succeeded',
            'amount' => 100,
            'paid_at' => now(),
        ]);

        $this->withSession($this->sessionFor($frontDesk))
            ->post('/admin/payments/'.$payment->id.'/refund', [
                'idempotency_key' => '33333333-3333-4333-8333-333333333333',
                'amount' => 10,
                'reason' => 'Not permitted',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('refunds', [
            'payment_id' => $payment->id,
        ]);
    }

    public function test_front_desk_cannot_reconcile_restaurant_refund(): void
    {
        $frontDesk = $this->admin('front_desk', 'frontdesk-reconcile@example.com');
        $order = $this->restaurantOrder('RO-FD-RBAC-2');
        $payment = Payment::query()->create([
            'idempotency_key' => '34343434-3434-4343-8343-343434343434',
            'restaurant_order_id' => $order->id,
            'method' => 'online_gateway',
            'status' => 'succeeded',
            'amount' => 100,
            'external_reference' => 'pay_frontdesk_forbidden',
            'paid_at' => now(),
        ]);
        $refund = Refund::query()->create([
            'idempotency_key' => '35353535-3535-4353-8353-353535353535',
            'payment_id' => $payment->id,
            'amount' => 10,
            'status' => 'pending',
        ]);

        $this->withSession($this->sessionFor($frontDesk))
            ->post('/admin/payments/refunds/'.$refund->id.'/reconcile')
            ->assertForbidden();

        $this->assertSame('pending', $refund->fresh()->status);
    }

    public function test_direct_urls_remain_backend_protected(): void
    {
        $accounts = $this->admin('accounts', 'accounts-direct@example.com');
        $this->withSession($this->sessionFor($accounts))
            ->get('/admin/front-desk')
            ->assertForbidden();

        $kitchen = $this->admin('kitchen', 'kitchen-direct@example.com');
        $this->withSession($this->sessionFor($kitchen))
            ->get('/admin/payments')
            ->assertForbidden();

        $restaurant = $this->admin('restaurant', 'restaurant-direct@example.com');
        $this->withSession($this->sessionFor($restaurant))
            ->get('/admin/reports')
            ->assertForbidden();
    }

    private function admin(string $role, string $email): AdminUser
    {
        return AdminUser::query()->create([
            'name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $email,
            'password_hash' => password_hash('test-password-123', PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function sessionFor(AdminUser $admin): array
    {
        return [
            'admin_user_id' => $admin->id,
            'admin_session_version' => $admin->session_version,
        ];
    }

    private function restaurantOrder(string $number): RestaurantOrder
    {
        return RestaurantOrder::query()->create([
            'order_number' => $number,
            'order_type' => 'takeaway',
            'status' => 'served',
            'payment_status' => 'unpaid',
            'subtotal' => 100,
            'tax' => 0,
            'total' => 100,
        ]);
    }
}
