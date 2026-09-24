<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StayRoom extends Model
{
    protected $fillable = ['stay_id', 'room_id', 'assigned_at', 'released_at'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function stay(): BelongsTo { return $this->belongsTo(Stay::class); }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
}
