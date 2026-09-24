<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RatePlan extends Model
{
    protected $fillable = ['code', 'name', 'includes_breakfast', 'is_active'];

    protected function casts(): array
    {
        return ['includes_breakfast' => 'boolean', 'is_active' => 'boolean'];
    }
}
