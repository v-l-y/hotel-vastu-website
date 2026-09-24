<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationNightRate extends Model
{
    protected $fillable = [
        'reservation_id', 'reservation_room_id', 'room_type_id',
        'rate_plan_id', 'stay_date', 'quantity', 'unit_rate', 'line_total',
        'tax_rate', 'tax_amount', 'gross_total',
    ];

    protected function casts(): array
    {
        return [
            'stay_date' => 'date',
            'unit_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'gross_total' => 'decimal:2',
        ];
    }
}
