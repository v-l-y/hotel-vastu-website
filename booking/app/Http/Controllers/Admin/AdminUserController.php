<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:admin_users,email'],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => ['required', 'string', 'min:12', 'max:500', 'confirmed'],
        ]);

        AdminUser::query()->create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
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
            return back()->withErrors(['user' => 'You cannot disable or remove your own administrator access.']);
        }

        $adminUser->update([
            'role' => $data['role'],
            'is_active' => $active,
        ]);

        return back()->with('status', 'Admin user updated.');
    }

    public function resetPassword(Request $request, AdminUser $adminUser): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:12', 'max:500', 'confirmed'],
        ]);

        $adminUser->update([
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);

        return back()->with('status', 'Password reset successfully.');
    }
}
