<?php

namespace App\Services;

use App\Models\PromotionCode;
use Illuminate\Support\Str;
use RuntimeException;

class PromotionService
{
    public function preview(?string $code, float $subtotal): ?array
    {
        $normalized = $this->normalize($code);
        if ($normalized === '') {
            return null;
        }

        $promotion = PromotionCode::query()->where('code', $normalized)->first();
        if ($promotion === null) {
            throw new RuntimeException('Promo code is invalid.');
        }

        return $this->snapshot($promotion, $subtotal);
    }

    public function previewQuote(?string $code, float $subtotal, float $tax): array
    {
        $snapshot = $this->preview($code, $subtotal);
        if ($snapshot === null) {
            throw new RuntimeException('Enter a promo code.');
        }

        $value = (float) $snapshot['discount_value'];
        $discount = $snapshot['discount_type'] === 'percent'
            ? round($subtotal * ($value / 100), 2)
            : $value;

        if ($snapshot['discount_max'] !== null) {
            $discount = min($discount, (float) $snapshot['discount_max']);
        }

        $discount = round(min($discount, $subtotal), 2);
        $taxFactor = $subtotal > 0 ? max(0, ($subtotal - $discount) / $subtotal) : 1;
        $discountedTax = round($tax * $taxFactor, 2);

        return [
            'code' => $snapshot['promotion_code_snapshot'],
            'discount' => $discount,
            'tax' => $discountedTax,
            'total' => round($subtotal - $discount + $discountedTax, 2),
        ];
    }

    public function consume(?string $code, float $subtotal): ?array
    {
        $normalized = $this->normalize($code);
        if ($normalized === '') {
            return null;
        }

        $promotion = PromotionCode::query()
            ->where('code', $normalized)
            ->lockForUpdate()
            ->first();

        if ($promotion === null) {
            throw new RuntimeException('Promo code is invalid.');
        }

        $snapshot = $this->snapshot($promotion, $subtotal);
        $promotion->increment('times_used');

        return $snapshot + ['promotion_code_id' => $promotion->id];
    }

    private function snapshot(PromotionCode $promotion, float $subtotal): array
    {
        if (! $promotion->is_active) {
            throw new RuntimeException('Promo code is inactive.');
        }

        $today = today();
        if ($promotion->starts_on !== null && $today->lt($promotion->starts_on)) {
            throw new RuntimeException('Promo code is not active yet.');
        }
        if ($promotion->ends_on !== null && $today->gt($promotion->ends_on)) {
            throw new RuntimeException('Promo code has expired.');
        }

        if ($promotion->usage_limit !== null && $promotion->times_used >= $promotion->usage_limit) {
            throw new RuntimeException('Promo code usage limit has been reached.');
        }

        if ($subtotal + 0.009 < (float) $promotion->min_subtotal) {
            throw new RuntimeException(
                'Promo code requires a minimum room subtotal of ₹'.number_format((float) $promotion->min_subtotal, 2).'.'
            );
        }

        if (! in_array($promotion->discount_type, ['fixed', 'percent'], true)) {
            throw new RuntimeException('Promo code discount configuration is invalid.');
        }

        $value = (float) $promotion->discount_value;
        if ($value <= 0 || ($promotion->discount_type === 'percent' && $value > 100)) {
            throw new RuntimeException('Promo code discount configuration is invalid.');
        }

        return [
            'promotion_code_snapshot' => $promotion->code,
            'discount_source' => 'promo',
            'discount_type' => $promotion->discount_type,
            'discount_value' => $value,
            'discount_max' => $promotion->max_discount === null ? null : (float) $promotion->max_discount,
            'discount_reason' => 'Promo code '.$promotion->code,
        ];
    }

    private function normalize(?string $code): string
    {
        return Str::upper(trim((string) $code));
    }
}
