<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantMenuItem extends Model
{
    protected $fillable = [
        'restaurant_category_id', 'name', 'price', 'is_vegetarian', 'is_active',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_vegetarian' => 'boolean', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RestaurantCategory::class, 'restaurant_category_id');
    }
}
