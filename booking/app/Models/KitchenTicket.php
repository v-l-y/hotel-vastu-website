<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenTicket extends Model
{
    protected $fillable = [
        'ticket_number', 'restaurant_order_id', 'status',
        'started_at', 'ready_at', 'served_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ready_at' => 'datetime', 'served_at' => 'datetime'];
    }

    public function items(): HasMany { return $this->hasMany(KitchenTicketItem::class); }
}
