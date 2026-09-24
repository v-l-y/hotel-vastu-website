<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'public_token', 'document_type', 'folio_id', 'restaurant_order_id',
        'is_gst_invoice',
        'supplier_name', 'supplier_address', 'supplier_gstin', 'supplier_state', 'supplier_state_code',
        'supplier_phone', 'supplier_email',
        'recipient_name', 'recipient_phone', 'recipient_email', 'recipient_gstin',
        'recipient_address', 'recipient_state', 'recipient_state_code',
        'place_of_supply', 'place_of_supply_code', 'reverse_charge',
        'subtotal', 'tax', 'cgst', 'sgst', 'igst',
        'total', 'paid', 'balance', 'issued_at', 'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_gst_invoice' => 'boolean',
            'reverse_charge' => 'boolean',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'cgst' => 'decimal:2',
            'sgst' => 'decimal:2',
            'igst' => 'decimal:2',
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'issued_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function creditNotes(): HasMany { return $this->hasMany(CreditNote::class); }
    public function folio(): BelongsTo { return $this->belongsTo(Folio::class); }
    public function restaurantOrder(): BelongsTo { return $this->belongsTo(RestaurantOrder::class); }
}
