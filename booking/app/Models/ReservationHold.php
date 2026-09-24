<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationHold extends Model
{
    protected $fillable = [
        'token', 'room_type_id', 'rate_plan_id', 'check_in_date', 'check_out_date',
        'quantity', 'adults', 'children', 'expires_at', 'converted_reservation_id',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('converted_reservation_id')->where('expires_at', '>', now());
    }

    public function roomType(): BelongsTo { return $this->belongsTo(RoomType::class); }
    public function ratePlan(): BelongsTo { return $this->belongsTo(RatePlan::class); }
    public function convertedReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'converted_reservation_id');
    }
}
