<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'idempotency_key', 'payment_id', 'amount', 'status',
        'reason', 'external_reference', 'refunded_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'refunded_at' => 'datetime'];
    }
}
