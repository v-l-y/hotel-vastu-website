<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantOrderItem extends Model
{
    protected $fillable = [
        'restaurant_order_id', 'restaurant_menu_item_id', 'item_name',
        'quantity', 'unit_price', 'line_total', 'note',
    ];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    }
}
