<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Payment;
use App\Models\Refund;

class FolioService
{
    public function addCharge(Folio $folio, array $charge): FolioCharge
    {
        if ($folio->status !== 'open') {
            throw new \RuntimeException('Charges can only be posted to an open folio.');
        }

        if (! empty($charge['source_key'])) {
            $existing = FolioCharge::query()->where('source_key', $charge['source_key'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $item = FolioCharge::query()->create([
            'folio_id' => $folio->id,
            'category' => $charge['category'],
            'description' => $charge['description'],
            'quantity' => $charge['quantity'] ?? 1,
            'subtotal' => $charge['subtotal'],
            'tax' => $charge['tax'] ?? 0,
            'amount' => $charge['amount'],
            'source_key' => $charge['source_key'] ?? null,
        ]);

        $this->recalculate($folio);

        return $item;
    }

    public function recalculate(Folio $folio): Folio
    {
        $charges = (float) FolioCharge::query()->where('folio_id', $folio->id)->sum('amount');
        $payments = (float) Payment::query()
            ->where('folio_id', $folio->id)
            ->where('status', 'succeeded')
            ->sum('amount');

        $paymentIds = Payment::query()->where('folio_id', $folio->id)->pluck('id');
        $refunds = $paymentIds->isEmpty()
            ? 0.0
            : (float) Refund::query()
                ->whereIn('payment_id', $paymentIds)
                ->where('status', 'succeeded')
                ->sum('amount');

        $folio->update([
            'charges_total' => round($charges, 2),
            'payments_total' => round($payments, 2),
            'refunds_total' => round($refunds, 2),
            'balance' => round($charges - $payments + $refunds, 2),
        ]);

        return $folio->fresh();
    }
}
