<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingVerification extends Model
{
    protected $fillable = [
        'reservation_hold_id', 'first_name', 'last_name', 'phone', 'email',
        'gstin', 'billing_address', 'billing_state', 'billing_state_code',
        'special_request', 'promo_code', 'code_hash', 'attempts', 'expires_at', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function hold(): BelongsTo
    {
        return $this->belongsTo(ReservationHold::class, 'reservation_hold_id');
    }
}
