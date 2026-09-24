<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model
{
    protected $fillable = [
        'name', 'applies_to', 'rate_percent', 'effective_from',
        'effective_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            });
    }
}
