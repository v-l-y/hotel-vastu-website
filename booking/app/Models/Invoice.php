<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'folio_id', 'subtotal', 'tax',
        'total', 'paid', 'balance', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2', 'tax' => 'decimal:2',
            'total' => 'decimal:2', 'paid' => 'decimal:2',
            'balance' => 'decimal:2', 'issued_at' => 'datetime',
        ];
    }

    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function creditNotes(): HasMany { return $this->hasMany(CreditNote::class); }
}
