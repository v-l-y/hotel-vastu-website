<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'category', 'description', 'sac_code', 'quantity',
        'subtotal', 'tax_rate_percent', 'tax',
        'cgst_rate_percent', 'cgst_amount',
        'sgst_rate_percent', 'sgst_amount',
        'igst_rate_percent', 'igst_amount',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_rate_percent' => 'decimal:4',
            'tax' => 'decimal:2',
            'cgst_rate_percent' => 'decimal:4',
            'cgst_amount' => 'decimal:2',
            'sgst_rate_percent' => 'decimal:4',
            'sgst_amount' => 'decimal:2',
            'igst_rate_percent' => 'decimal:4',
            'igst_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
