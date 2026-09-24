<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $fillable = [
        'booking_number', 'check_in_date', 'check_out_date', 'adults', 'children',
        'status', 'source', 'special_request', 'subtotal', 'tax', 'discount',
        'total', 'payment_status', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'expires_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }
}
