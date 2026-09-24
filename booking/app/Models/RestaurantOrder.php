<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantOrder extends Model
{
    protected $fillable = [
        'order_number', 'idempotency_key', 'order_type', 'folio_id', 'restaurant_table_id',
        'guest_name', 'guest_phone', 'guest_email', 'guest_gstin',
        'guest_billing_address', 'guest_billing_state', 'guest_billing_state_code',
        'status', 'payment_status', 'subtotal', 'tax', 'total',
    ];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function items(): HasMany { return $this->hasMany(RestaurantOrderItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function kitchenTicket(): HasOne { return $this->hasOne(KitchenTicket::class); }
    public function invoice(): HasOne { return $this->hasOne(Invoice::class); }
    public function restaurantTable(): BelongsTo { return $this->belongsTo(RestaurantTable::class); }
    public function folio(): BelongsTo { return $this->belongsTo(Folio::class); }
}
