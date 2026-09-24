<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    protected $fillable = [
        'credit_note_number', 'invoice_id', 'refund_id',
        'amount', 'reason', 'issued_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'issued_at' => 'datetime'];
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function refund(): BelongsTo { return $this->belongsTo(Refund::class); }
    public function items(): HasMany { return $this->hasMany(CreditNoteItem::class); }
}
