<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folio extends Model
{
    protected $fillable = [
        'stay_id', 'reservation_id', 'status', 'charges_total',
        'payments_total', 'refunds_total', 'balance',
    ];

    protected function casts(): array
    {
        return [
            'charges_total' => 'decimal:2',
            'payments_total' => 'decimal:2',
            'refunds_total' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function charges(): HasMany { return $this->hasMany(FolioCharge::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function stay(): BelongsTo { return $this->belongsTo(Stay::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
}
