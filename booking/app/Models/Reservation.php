<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    protected $fillable = [
        'booking_number', 'public_token', 'check_in_date', 'check_out_date',
        'adults', 'children', 'status', 'source', 'special_request',
        'subtotal', 'tax', 'discount', 'total', 'payment_status',
        'promotion_code_id', 'promotion_code_snapshot', 'discount_source',
        'discount_type', 'discount_value', 'discount_max', 'discount_reason',
        'discount_authorized_by',
        'pricing_status', 'expires_at', 'pre_arrival_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'expires_at' => 'datetime',
            'pre_arrival_reminder_sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_max' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    public function guestLinks(): HasMany
    {
        return $this->hasMany(ReservationGuest::class);
    }

    public function nightRates(): HasMany
    {
        return $this->hasMany(ReservationNightRate::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(ReservationFeedback::class);
    }
}
