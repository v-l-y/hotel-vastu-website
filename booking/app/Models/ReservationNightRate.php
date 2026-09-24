<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationNightRate extends Model
{
    protected $fillable = [
        'reservation_id', 'reservation_room_id', 'room_type_id',
        'rate_plan_id', 'stay_date', 'quantity', 'unit_rate', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'stay_date' => 'date',
            'unit_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}
