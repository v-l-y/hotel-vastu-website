<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayOrder extends Model
{
    protected $fillable = [
        'provider', 'reservation_id', 'provider_order_id', 'provider_payment_id',
        'amount_subunits', 'currency', 'status',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
