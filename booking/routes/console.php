<?php

use App\Models\AdminUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Artisan::command('hotel:status', function () {
    $this->info('Hotel Vastu booking application is ready.');
});

Artisan::command('hotel:infra-check', function () {
    if (config('session.driver') !== 'redis') {
        $this->error('SESSION_DRIVER must be redis.');
        return 1;
    }

    if (config('cache.default') !== 'redis') {
        $this->error('CACHE_STORE must be redis.');
        return 1;
    }

    if (config('cache.limiter') !== 'redis') {
        $this->error('CACHE_LIMITER must be redis.');
        return 1;
    }

    try {
        DB::select('SELECT 1');

        $key = 'hotel-vastu-infra-check-'.bin2hex(random_bytes(8));
        cache()->put($key, 'ok', 30);
        $value = cache()->pull($key);

        if ($value !== 'ok') {
            $this->error('Redis cache round-trip failed.');
            return 1;
        }
    } catch (\Throwable $exception) {
        $this->error('Infrastructure check failed: '.$exception->getMessage());
        return 1;
    }

    $this->info('MySQL + Redis + session/cache configuration are healthy.');
    return 0;
});

Artisan::command('admin:create {email} {--name=} {--role=administrator}', function () {
    $email = strtolower(trim((string) $this->argument('email')));
    $name = trim((string) ($this->option('name') ?: 'Hotel Administrator'));
    $role = (string) $this->option('role');
    $allowed = ['administrator', 'front_desk', 'restaurant', 'kitchen', 'accounts'];

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('A valid email address is required.');
        return 1;
    }

    if (! in_array($role, $allowed, true)) {
        $this->error('Role must be one of: '.implode(', ', $allowed));
        return 1;
    }

    $password = (string) $this->secret(
        'Password (12+ chars with upper, lower, number and symbol)'
    );

    $strong = strlen($password) >= 12
        && preg_match('/[a-z]/', $password)
        && preg_match('/[A-Z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);

    if (! $strong) {
        $this->error(
            'Password must be at least 12 characters and include upper/lowercase letters, a number and a symbol.'
        );
        return 1;
    }

    $confirm = (string) $this->secret('Confirm password');

    if (! hash_equals($password, $confirm)) {
        $this->error('Passwords do not match.');
        return 1;
    }

    $admin = AdminUser::query()->firstOrNew(['email' => $email]);
    $nextVersion = $admin->exists
        ? ((int) $admin->session_version + 1)
        : 1;

    $admin->forceFill([
        'name' => $name,
        'password_hash' => Hash::make($password),
        'role' => $role,
        'is_active' => true,
        'session_version' => $nextVersion,
        'password_changed_at' => now(),
    ])->save();

    $this->info('Admin user saved. Existing sessions for this account were revoked.');
    return 0;
});
