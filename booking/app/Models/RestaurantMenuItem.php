<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantMenuItem extends Model
{
    protected $fillable = [
        'restaurant_category_id', 'name', 'price', 'is_vegetarian', 'is_active',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_vegetarian' => 'boolean', 'is_active' => 'boolean'];
    }
}
