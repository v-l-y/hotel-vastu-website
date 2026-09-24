<?php

namespace Tests\Feature;

use App\Models\AdminAuthEvent;
use App\Models\AdminUser;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_admin_login_is_audited_without_authenticating(): void
    {
        $admin = $this->admin('security@example.com');

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'Wrong!Password123',
        ])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('admin_user_id');

        $this->assertDatabaseHas('admin_auth_events', [
            'admin_user_id' => $admin->id,
            'event_type' => 'login_failed',
        ]);
    }

    public function test_login_returns_user_to_requested_admin_page(): void
    {
        $admin = $this->admin('intended@example.com');

        $this->get('/admin/reports')
            ->assertRedirect('/admin/login');

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'Strong!Password123',
        ])->assertRedirect('/admin/reports');
    }

    public function test_stale_session_version_is_rejected(): void
    {
        $admin = $this->admin('stale@example.com');
        $oldVersion = $admin->session_version;

        $admin->forceFill([
            'session_version' => $oldVersion + 1,
        ])->save();

        $this->withSession([
            'admin_user_id' => $admin->id,
            'admin_session_version' => $oldVersion,
        ])
            ->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_enabled_two_factor_requires_and_accepts_totp_challenge(): void
    {
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $admin = $this->admin('twofactor@example.com');

        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_enabled_at' => now(),
        ])->save();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'Strong!Password123',
        ])
            ->assertRedirect('/admin/two-factor')
            ->assertSessionHas('pending_admin_2fa_id', $admin->id)
            ->assertSessionMissing('admin_user_id');

        $this->post('/admin/two-factor', [
            'code' => $totp->currentCode($secret),
        ])
            ->assertRedirect('/admin')
            ->assertSessionHas('admin_user_id', $admin->id)
            ->assertSessionHas('admin_session_version', $admin->session_version);

        $this->assertDatabaseHas('admin_auth_events', [
            'admin_user_id' => $admin->id,
            'event_type' => 'login_success',
        ]);
    }

    public function test_password_reset_revokes_previous_sessions(): void
    {
        $actor = $this->admin('actor@example.com');
        $target = $this->admin('target@example.com');
        $oldVersion = $target->session_version;
        $newPassword = 'Changed!Password456';

        $this->withSession($this->sessionFor($actor))
            ->post('/admin/users/'.$target->id.'/password', [
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertRedirect();

        $target->refresh();

        $this->assertSame($oldVersion + 1, $target->session_version);
        $this->assertTrue(Hash::check($newPassword, $target->password_hash));

        $this->withSession([
            'admin_user_id' => $target->id,
            'admin_session_version' => $oldVersion,
        ])
            ->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_password_policy_rejects_weak_passwords(): void
    {
        $actor = $this->admin('policy-admin@example.com');

        $this->withSession($this->sessionFor($actor))
            ->post('/admin/users', [
                'name' => 'Weak User',
                'email' => 'weak@example.com',
                'role' => 'front_desk',
                'password' => 'onlylowercase123',
                'password_confirmation' => 'onlylowercase123',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('admin_users', [
            'email' => 'weak@example.com',
        ]);
    }

    private function admin(string $email): AdminUser
    {
        return AdminUser::query()->create([
            'name' => 'Security Admin',
            'email' => $email,
            'password_hash' => Hash::make('Strong!Password123'),
            'role' => 'administrator',
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
