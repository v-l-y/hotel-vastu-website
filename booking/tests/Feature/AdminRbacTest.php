<?php

namespace Tests\Feature;

use App\Models\AdminUser;
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
}
