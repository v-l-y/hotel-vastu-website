<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FolioCharge extends Model
{
    protected $fillable = [
        'folio_id', 'category', 'description', 'quantity',
        'subtotal', 'tax', 'amount', 'source_key',
    ];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'amount' => 'decimal:2'];
    }
}
