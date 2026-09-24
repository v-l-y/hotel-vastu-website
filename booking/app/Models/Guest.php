<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    protected $fillable = [
        'first_name', 'last_name', 'phone', 'email',
        'gstin', 'billing_address', 'billing_state', 'billing_state_code',
    ];

    public function reservationLinks(): HasMany
    {
        return $this->hasMany(ReservationGuest::class);
    }
}
