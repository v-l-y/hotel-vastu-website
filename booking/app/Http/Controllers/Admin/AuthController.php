<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $admin = AdminUser::query()
            ->where('email', strtolower($data['email']))
            ->where('is_active', true)
            ->first();

        if ($admin === null || ! password_verify($data['password'], $admin->password_hash)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid administrator credentials.']);
        }

        $request->session()->regenerate();
        $request->session()->put('admin_user_id', $admin->id);
        $admin->update(['last_login_at' => now()]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
