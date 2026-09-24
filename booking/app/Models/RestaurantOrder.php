<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantOrder extends Model
{
    protected $fillable = [
        'order_number', 'order_type', 'folio_id', 'restaurant_table_id',
        'guest_name', 'guest_phone', 'status', 'payment_status',
        'subtotal', 'tax', 'total',
    ];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function items(): HasMany { return $this->hasMany(RestaurantOrderItem::class); }
    public function kitchenTicket(): HasOne { return $this->hasOne(KitchenTicket::class); }
}
