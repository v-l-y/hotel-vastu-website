<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUser extends Model
{
    protected $fillable = ['name', 'email', 'password_hash', 'role', 'is_active', 'last_login_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_login_at' => 'datetime'];
    }

    protected $hidden = ['password_hash'];
}
