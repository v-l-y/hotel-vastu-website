<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminAuthEvent;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    private const ROLES = ['administrator', 'front_desk', 'restaurant', 'kitchen', 'accounts'];

    public function index(): View
    {
        return view('admin.users', [
            'users' => AdminUser::query()->orderBy('name')->get(),
            'auditLogs' => AdminAuditLog::query()
                ->with('adminUser')
                ->latest('id')
                ->limit(100)
                ->get(),
            'authEvents' => AdminAuthEvent::query()
                ->with('adminUser')
                ->latest('id')
                ->limit(100)
                ->get(),
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:admin_users,email'],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => ['required', 'string', $this->passwordRule(), 'confirmed'],
        ]);

        AdminUser::query()->create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'password_hash' => Hash::make($data['password']),
            'password_changed_at' => now(),
            'is_active' => true,
        ]);

        return back()->with('status', 'Admin user created.');
    }

    public function update(Request $request, AdminUser $adminUser): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $current = $request->attributes->get('admin_user');
        $isSelf = $current instanceof AdminUser && $current->id === $adminUser->id;
        $active = $request->boolean('is_active');

        if ($isSelf && (! $active || $data['role'] !== 'administrator')) {
            return back()->withErrors([
                'user' => 'You cannot disable or remove your own administrator access.',
            ]);
        }

        $accessChanged = $adminUser->role !== $data['role']
            || $adminUser->is_active !== $active;

        $adminUser->forceFill([
            'role' => $data['role'],
            'is_active' => $active,
            'session_version' => $accessChanged
                ? $adminUser->session_version + 1
                : $adminUser->session_version,
        ])->save();

        return back()->with('status', 'Admin user updated.');
    }

    public function resetPassword(Request $request, AdminUser $adminUser): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', $this->passwordRule(), 'confirmed'],
        ]);

        $newVersion = $adminUser->session_version + 1;

        $adminUser->forceFill([
            'password_hash' => Hash::make($data['password']),
            'password_changed_at' => now(),
            'session_version' => $newVersion,
        ])->save();

        $current = $request->attributes->get('admin_user');

        if ($current instanceof AdminUser && $current->id === $adminUser->id) {
            $request->session()->put('admin_session_version', $newVersion);
        }

        return back()->with(
            'status',
            'Password reset successfully. Existing sessions were revoked.'
        );
    }

    public function resetTwoFactor(Request $request, AdminUser $adminUser): RedirectResponse
    {
        $current = $request->attributes->get('admin_user');

        if ($current instanceof AdminUser && $current->id === $adminUser->id) {
            return back()->withErrors([
                'user' => 'Use Security settings to disable your own two-factor authentication.',
            ]);
        }

        $adminUser->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null,
            'session_version' => $adminUser->session_version + 1,
        ])->save();

        return back()->with(
            'status',
            'Two-factor authentication reset. Existing sessions were revoked.'
        );
    }

    private function passwordRule(): Password
    {
        return Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols();
    }
}
