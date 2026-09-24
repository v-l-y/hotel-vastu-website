<?php

use App\Models\AdminUser;
use Illuminate\Support\Facades\Artisan;

Artisan::command('hotel:status', function () {
    $this->info('Hotel Vastu booking application is ready.');
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

    $password = (string) $this->secret('Password (minimum 12 characters)');
    if (strlen($password) < 12) {
        $this->error('Password must contain at least 12 characters.');
        return 1;
    }

    $confirm = (string) $this->secret('Confirm password');
    if (! hash_equals($password, $confirm)) {
        $this->error('Passwords do not match.');
        return 1;
    }

    AdminUser::query()->updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => true,
        ]
    );

    $this->info('Admin user saved.');
    return 0;
});
