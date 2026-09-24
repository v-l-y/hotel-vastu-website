<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUser extends Model
{
    protected $attributes = [
        'session_version' => 1,
    ];

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'is_active',
        'session_version',
        'two_factor_secret',
        'two_factor_enabled_at',
        'last_login_at',
        'password_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'session_version' => 'integer',
            'two_factor_secret' => 'encrypted',
            'two_factor_enabled_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    protected $hidden = ['password_hash', 'two_factor_secret'];

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled_at !== null
            && filled($this->two_factor_secret);
    }
}
