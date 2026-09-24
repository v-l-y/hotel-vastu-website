<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomRate extends Model
{
    protected $fillable = [
        'room_type_id', 'rate_plan_id', 'starts_on', 'ends_on',
        'nightly_rate', 'min_stay', 'max_stay',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'nightly_rate' => 'decimal:2',
        ];
    }
}
