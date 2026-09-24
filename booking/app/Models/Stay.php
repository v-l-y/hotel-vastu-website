<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Stay extends Model
{
    protected $fillable = ['reservation_id', 'status', 'checked_in_at', 'checked_out_at'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'checked_out_at' => 'datetime'];
    }

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function rooms(): HasMany { return $this->hasMany(StayRoom::class); }
    public function guests(): HasMany { return $this->hasMany(StayGuest::class); }
    public function folio(): HasOne { return $this->hasOne(Folio::class); }
}
