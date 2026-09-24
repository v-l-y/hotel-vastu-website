<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuthEvent;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('admin_user_id')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:500'],
        ]);

        $email = strtolower(trim($data['email']));

        $admin = AdminUser::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($admin === null || ! Hash::check($data['password'], $admin->password_hash)) {
            AdminAuthEvent::record($request, 'login_failed', $admin, $email);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid administrator credentials.']);
        }

        if (Hash::needsRehash($admin->password_hash)) {
            $admin->forceFill([
                'password_hash' => Hash::make($data['password']),
            ])->save();
        }

        if ($admin->hasTwoFactorEnabled()) {
            $request->session()->regenerate();
            $request->session()->forget([
                'admin_user_id',
                'admin_session_version',
            ]);
            $request->session()->put([
                'pending_admin_2fa_id' => $admin->id,
                'pending_admin_2fa_version' => $admin->session_version,
            ]);

            AdminAuthEvent::record($request, 'password_verified', $admin, $email);

            return redirect()->route('admin.two-factor.challenge');
        }

        return $this->completeLogin($request, $admin);
    }

    public function logout(Request $request): RedirectResponse
    {
        $admin = $request->attributes->get('admin_user');

        if ($admin instanceof AdminUser) {
            AdminAuthEvent::record($request, 'logout', $admin, $admin->email);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function completeLogin(Request $request, AdminUser $admin): RedirectResponse
    {
        $request->session()->regenerate();
        $request->session()->forget([
            'pending_admin_2fa_id',
            'pending_admin_2fa_version',
            'two_factor_setup_secret',
        ]);
        $request->session()->put([
            'admin_user_id' => $admin->id,
            'admin_session_version' => $admin->session_version,
        ]);

        $admin->forceFill(['last_login_at' => now()])->save();

        AdminAuthEvent::record($request, 'login_success', $admin, $admin->email);

        return redirect()->intended(route('admin.dashboard'));
    }
}
