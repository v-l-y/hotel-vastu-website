<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuthEvent;
use App\Models\AdminUser;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function challenge(Request $request): View|RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if ($admin === null) {
            return redirect()->route('admin.login');
        }

        return view('admin.two-factor-challenge', ['admin' => $admin]);
    }

    public function verifyChallenge(Request $request, Totp $totp): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $admin = $this->pendingAdmin($request);

        if ($admin === null) {
            return redirect()->route('admin.login');
        }

        if (! $totp->verify((string) $admin->two_factor_secret, $data['code'])) {
            AdminAuthEvent::record(
                $request,
                'two_factor_failed',
                $admin,
                $admin->email
            );

            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

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

    public function settings(Request $request, Totp $totp): View
    {
        /** @var AdminUser $admin */
        $admin = $request->attributes->get('admin_user');
        $setupSecret = $request->session()->get('two_factor_setup_secret');

        return view('admin.security', [
            'admin' => $admin,
            'setupSecret' => $setupSecret,
            'provisioningUri' => $setupSecret
                ? $totp->provisioningUri($admin->email, $setupSecret)
                : null,
        ]);
    }

    public function beginSetup(Request $request, Totp $totp): RedirectResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->attributes->get('admin_user');

        if ($admin->hasTwoFactorEnabled()) {
            return back()->with('status', 'Two-factor authentication is already enabled.');
        }

        $request->session()->put(
            'two_factor_setup_secret',
            $totp->generateSecret()
        );

        return back()->with('status', 'Authenticator setup key generated.');
    }

    public function enable(Request $request, Totp $totp): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'max:500'],
            'code' => ['required', 'digits:6'],
        ]);

        /** @var AdminUser $admin */
        $admin = $request->attributes->get('admin_user');
        $secret = (string) $request->session()->get('two_factor_setup_secret', '');

        if ($secret === '') {
            return back()->withErrors([
                'code' => 'Generate an authenticator setup key first.',
            ]);
        }

        if (! Hash::check($data['password'], $admin->password_hash)) {
            return back()->withErrors([
                'password' => 'Current password is incorrect.',
            ]);
        }

        if (! $totp->verify($secret, $data['code'])) {
            return back()->withErrors([
                'code' => 'Invalid authentication code.',
            ]);
        }

        $newVersion = $admin->session_version + 1;

        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_enabled_at' => now(),
            'session_version' => $newVersion,
        ])->save();

        $request->session()->put('admin_session_version', $newVersion);
        $request->session()->forget('two_factor_setup_secret');

        AdminAuthEvent::record(
            $request,
            'two_factor_enabled',
            $admin,
            $admin->email
        );

        return back()->with('status', 'Two-factor authentication enabled.');
    }

    public function disable(Request $request, Totp $totp): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'max:500'],
            'code' => ['required', 'digits:6'],
        ]);

        /** @var AdminUser $admin */
        $admin = $request->attributes->get('admin_user');

        if (! $admin->hasTwoFactorEnabled()) {
            return back()->with('status', 'Two-factor authentication is already disabled.');
        }

        if (! Hash::check($data['password'], $admin->password_hash)) {
            return back()->withErrors([
                'password' => 'Current password is incorrect.',
            ]);
        }

        if (! $totp->verify((string) $admin->two_factor_secret, $data['code'])) {
            return back()->withErrors([
                'code' => 'Invalid authentication code.',
            ]);
        }

        $newVersion = $admin->session_version + 1;

        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null,
            'session_version' => $newVersion,
        ])->save();

        $request->session()->put('admin_session_version', $newVersion);
        $request->session()->forget('two_factor_setup_secret');

        AdminAuthEvent::record(
            $request,
            'two_factor_disabled',
            $admin,
            $admin->email
        );

        return back()->with('status', 'Two-factor authentication disabled.');
    }

    private function pendingAdmin(Request $request): ?AdminUser
    {
        $adminId = $request->session()->get('pending_admin_2fa_id');
        $version = $request->session()->get('pending_admin_2fa_version');

        if ($adminId === null || $version === null) {
            return null;
        }

        $admin = AdminUser::query()
            ->whereKey($adminId)
            ->where('is_active', true)
            ->first();

        if (
            $admin === null
            || ! $admin->hasTwoFactorEnabled()
            || (int) $version !== (int) $admin->session_version
        ) {
            $request->session()->forget([
                'pending_admin_2fa_id',
                'pending_admin_2fa_version',
            ]);

            return null;
        }

        return $admin;
    }
}
