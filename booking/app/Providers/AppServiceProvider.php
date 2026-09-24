<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('admin-login', function (Request $request): array {
            $email = strtolower(trim((string) $request->input('email')));
            $identity = hash('sha256', $email.'|'.$request->ip());

            return [
                Limit::perMinute(6)->by('admin-login:'.$identity),
                Limit::perMinute(30)->by('admin-login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('admin-two-factor', function (Request $request): array {
            $pendingId = (string) $request->session()->get('pending_admin_2fa_id', 'unknown');

            return [
                Limit::perMinute(8)->by('admin-2fa:'.$pendingId.'|'.$request->ip()),
                Limit::perMinute(30)->by('admin-2fa-ip:'.$request->ip()),
            ];
        });
    }
}
