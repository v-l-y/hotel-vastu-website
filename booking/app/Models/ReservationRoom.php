<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationRoom extends Model
{
    protected $fillable = ['reservation_id', 'room_type_id', 'rate_plan_id', 'quantity', 'nightly_rate'];

    protected function casts(): array
    {
        return ['nightly_rate' => 'decimal:2'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
