<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Payment;
use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaginationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_table_uses_real_pagination_and_fixed_icon_boxes(): void
    {
        $admin = $this->admin('administrator', 'admin-pagination@example.com');

        foreach (range(1, 16) as $index) {
            $this->admin('front_desk', sprintf('staff-%02d@example.com', $index), 'Staff '.sprintf('%02d', $index));
        }

        $this->withSession($this->sessionFor($admin))
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('ui-icon-box', false)
            ->assertSee('Showing 1–15 of 17')
            ->assertSee('Next →');

        $this->withSession($this->sessionFor($admin))
            ->get('/admin/users?users_page=2')
            ->assertOk()
            ->assertSee('Showing 16–17 of 17');
    }

    public function test_setup_menu_registry_is_paginated(): void
    {
        $admin = $this->admin('administrator', 'setup-pagination@example.com');
        $category = RestaurantCategory::query()->create([
            'name' => 'Pagination Menu',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        foreach (range(1, 16) as $index) {
            RestaurantMenuItem::query()->create([
                'restaurant_category_id' => $category->id,
                'name' => 'Menu Item '.sprintf('%02d', $index),
                'price' => 100 + $index,
                'is_vegetarian' => true,
                'is_active' => true,
            ]);
        }

        $this->withSession($this->sessionFor($admin))
            ->get('/admin/setup?menu_page=2')
            ->assertOk()
            ->assertSee('Menu registry')
            ->assertSee('Menu Item 16')
            ->assertSee('Showing 16–16 of 16');
    }

    public function test_restaurant_completed_order_history_is_paginated(): void
    {
        $admin = $this->admin('administrator', 'restaurant-pagination@example.com');

        foreach (range(1, 21) as $index) {
            RestaurantOrder::query()->create([
                'order_number' => 'RO-PAGE-'.sprintf('%02d', $index),
                'order_type' => 'takeaway',
                'status' => 'served',
                'payment_status' => 'paid',
                'subtotal' => 100,
                'tax' => 0,
                'total' => 100,
            ]);
        }

        $this->withSession($this->sessionFor($admin))
            ->get('/admin/restaurant?history_page=2')
            ->assertOk()
            ->assertSee('Completed order history')
            ->assertSee('RO-PAGE-01')
            ->assertSee('Showing 21–21 of 21');
    }

    public function test_payment_history_is_paginated(): void
    {
        $admin = $this->admin('administrator', 'payment-pagination@example.com');

        foreach (range(1, 26) as $index) {
            Payment::query()->create([
                'idempotency_key' => sprintf('00000000-0000-4000-8000-%012d', $index),
                'method' => 'cash',
                'status' => 'succeeded',
                'amount' => 100 + $index,
                'paid_at' => now(),
            ]);
        }

        $this->withSession($this->sessionFor($admin))
            ->get('/admin/payments?payments_page=2')
            ->assertOk()
            ->assertSee('Recent payments / refunds')
            ->assertSee('Payment #1')
            ->assertSee('Showing 26–26 of 26');
    }

    private function admin(string $role, string $email, string $name = 'Pagination Admin'): AdminUser
    {
        return AdminUser::query()->create([
            'name' => $name,
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
}
