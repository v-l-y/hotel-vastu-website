<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    protected $fillable = ['first_name', 'last_name', 'phone', 'email'];

    public function reservationLinks(): HasMany
    {
        return $this->hasMany(ReservationGuest::class);
    }
}
