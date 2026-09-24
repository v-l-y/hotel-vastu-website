<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'idempotency_key', 'reservation_id', 'folio_id', 'restaurant_order_id',
        'method', 'status', 'amount', 'external_reference', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function refunds(): HasMany { return $this->hasMany(Refund::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function folio(): BelongsTo { return $this->belongsTo(Folio::class); }
    public function restaurantOrder(): BelongsTo { return $this->belongsTo(RestaurantOrder::class); }
}
