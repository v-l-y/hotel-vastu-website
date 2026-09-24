<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantTable extends Model
{
    protected $fillable = ['code', 'name', 'capacity', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
