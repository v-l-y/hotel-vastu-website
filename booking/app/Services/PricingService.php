<?php

namespace App\Services;

use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationNightRate;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\TaxRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PricingService
{
    public function quote(
        int $roomTypeId,
        int $ratePlanId,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $quantity,
        ?int $stayLengthNights = null
    ): array {
        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw new RuntimeException('Check-out must be after check-in.');
        }

        $roomType = RoomType::query()->whereKey($roomTypeId)->where('is_active', true)->firstOrFail();
        RatePlan::query()->whereKey($ratePlanId)->where('is_active', true)->firstOrFail();

        $nights = $checkIn->diffInDays($checkOut);
        $validatedStayLength = $stayLengthNights ?? $nights;
        if ($validatedStayLength < $nights) {
            throw new RuntimeException('Stay-length context cannot be shorter than the quoted date range.');
        }

        $breakdown = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $taxRates = [];

        for ($date = $checkIn; $date->lessThan($checkOut); $date = $date->addDay()) {
            $rate = RoomRate::query()
                ->where('room_type_id', $roomTypeId)
                ->where('rate_plan_id', $ratePlanId)
                ->whereDate('starts_on', '<=', $date->toDateString())
                ->whereDate('ends_on', '>=', $date->toDateString())
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->first();

            if ($rate !== null) {
                if (
                    $validatedStayLength < $rate->min_stay
                    || ($rate->max_stay !== null && $validatedStayLength > $rate->max_stay)
                ) {
                    throw new RuntimeException('The selected rate plan is not valid for this stay length.');
                }
                $unitRate = (float) $rate->nightly_rate;
            } elseif ($roomType->base_rate !== null) {
                $unitRate = (float) $roomType->base_rate;
            } else {
                throw new RuntimeException('No price is configured for the selected room and dates.');
            }

            $lineTotal = round($unitRate * $quantity, 2);
            $taxRate = (float) TaxRule::query()
                ->effectiveOn($date->toDateString())
                ->whereIn('applies_to', ['hotel', 'all'])
                ->sum('rate_percent');
            $taxAmount = round($lineTotal * ($taxRate / 100), 2);
            $grossTotal = round($lineTotal + $taxAmount, 2);

            $subtotal = round($subtotal + $lineTotal, 2);
            $taxTotal = round($taxTotal + $taxAmount, 2);
            $taxRates[] = $taxRate;

            $breakdown[] = [
                'stay_date' => $date->toDateString(),
                'unit_rate' => $unitRate,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'gross_total' => $grossTotal,
            ];
        }

        $uniqueTaxRates = array_values(array_unique(array_map(
            fn ($rate) => number_format((float) $rate, 4, '.', ''),
            $taxRates
        )));

        return [
            'subtotal' => $subtotal,
            'tax_rate' => count($uniqueTaxRates) === 1 ? (float) $uniqueTaxRates[0] : null,
            'tax_rates' => array_map('floatval', $uniqueTaxRates),
            'tax' => $taxTotal,
            'total' => round($subtotal + $taxTotal, 2),
            'nights' => $breakdown,
        ];
    }

    public function priceReservation(Reservation $reservation): Reservation
    {
        $reservation->loadMissing('rooms');
        ReservationNightRate::query()->where('reservation_id', $reservation->id)->delete();

        $subtotal = 0.0;
        $tax = 0.0;

        foreach ($reservation->rooms as $reservationRoom) {
            if ($reservationRoom->rate_plan_id === null) {
                throw new RuntimeException('A rate plan is required before confirming this reservation.');
            }

            $quote = $this->quote(
                $reservationRoom->room_type_id,
                $reservationRoom->rate_plan_id,
                CarbonImmutable::parse($reservation->check_in_date),
                CarbonImmutable::parse($reservation->check_out_date),
                $reservationRoom->quantity
            );

            foreach ($quote['nights'] as $night) {
                ReservationNightRate::query()->create([
                    'reservation_id' => $reservation->id,
                    'reservation_room_id' => $reservationRoom->id,
                    'room_type_id' => $reservationRoom->room_type_id,
                    'rate_plan_id' => $reservationRoom->rate_plan_id,
                    'stay_date' => $night['stay_date'],
                    'quantity' => $night['quantity'],
                    'unit_rate' => $night['unit_rate'],
                    'line_total' => $night['line_total'],
                    'tax_rate' => $night['tax_rate'],
                    'tax_amount' => $night['tax_amount'],
                    'gross_total' => $night['gross_total'],
                ]);
            }

            $reservationRoom->update([
                'nightly_rate' => $quote['nights'][0]['unit_rate'] ?? null,
            ]);

            $subtotal = round($subtotal + $quote['subtotal'], 2);
            $tax = round($tax + $quote['tax'], 2);
        }

        $discount = $this->calculateDiscount($reservation, $subtotal);
        $taxFactor = $subtotal > 0 ? max(0, ($subtotal - $discount) / $subtotal) : 1;
        $discountedTax = round($tax * $taxFactor, 2);

        $reservation->update([
            'subtotal' => $subtotal,
            'tax' => $discountedTax,
            'discount' => $discount,
            'total' => round($subtotal - $discount + $discountedTax, 2),
            'pricing_status' => 'priced',
        ]);

        return $reservation->fresh(['rooms']);
    }

    public function applyManualDiscount(
        Reservation $reservation,
        string $type,
        float $value,
        string $reason,
        ?int $adminUserId
    ): Reservation {
        if (! in_array($type, ['fixed', 'percent'], true)) {
            throw new RuntimeException('Discount type must be fixed or percent.');
        }

        $value = round($value, 2);
        if ($value <= 0 || ($type === 'percent' && $value > 100)) {
            throw new RuntimeException('Choose a valid discount value.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Discount reason is required.');
        }

        return DB::transaction(function () use ($reservation, $type, $value, $reason, $adminUserId) {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->status !== 'confirmed') {
                throw new RuntimeException('Manual discount can only be changed before guest check-in.');
            }

            if ($reservation->discount_source === 'promo') {
                throw new RuntimeException('A promo-code booking cannot be replaced with a manual discount.');
            }

            $reservation->update([
                'promotion_code_id' => null,
                'promotion_code_snapshot' => null,
                'discount_source' => 'manual',
                'discount_type' => $type,
                'discount_value' => $value,
                'discount_max' => null,
                'discount_reason' => $reason,
                'discount_authorized_by' => $adminUserId,
                'pricing_status' => 'pending',
            ]);

            return $this->priceReservation($reservation->fresh('rooms'));
        }, 3);
    }

    private function calculateDiscount(Reservation $reservation, float $subtotal): float
    {
        $type = (string) ($reservation->discount_type ?? '');
        $value = (float) ($reservation->discount_value ?? 0);

        if ($type === '' || $value <= 0 || $subtotal <= 0) {
            return 0.0;
        }

        if ($type === 'fixed') {
            $discount = $value;
        } elseif ($type === 'percent') {
            if ($value > 100) {
                throw new RuntimeException('Percentage discount cannot exceed 100%.');
            }
            $discount = round($subtotal * ($value / 100), 2);
        } else {
            throw new RuntimeException('Reservation discount configuration is invalid.');
        }

        if ($reservation->discount_max !== null) {
            $discount = min($discount, (float) $reservation->discount_max);
        }

        return round(min($discount, $subtotal), 2);
    }
}
