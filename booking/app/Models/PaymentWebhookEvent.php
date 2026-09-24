<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = [
        'provider', 'event_hash', 'event_name', 'provider_order_id',
        'provider_payment_id', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
