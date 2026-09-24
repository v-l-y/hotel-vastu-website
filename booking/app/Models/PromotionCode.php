<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionCode extends Model
{
    protected $fillable = [
        'code', 'name', 'discount_type', 'discount_value', 'max_discount',
        'min_subtotal', 'starts_on', 'ends_on', 'usage_limit', 'times_used', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'usage_limit' => 'integer',
            'times_used' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
