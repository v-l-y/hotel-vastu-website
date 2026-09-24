<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PaymentService
{
    public function __construct(
        private FolioService $folios,
        private CreditNoteService $creditNotes,
        private RazorpayClient $razorpay
    ) {
    }

    public function record(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $existing = Payment::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                return $existing;
            }

            $targets = array_filter([
                'reservation_id' => $data['reservation_id'] ?? null,
                'folio_id' => $data['folio_id'] ?? null,
                'restaurant_order_id' => $data['restaurant_order_id'] ?? null,
            ]);

            if (count($targets) !== 1) {
                throw new RuntimeException('A payment must target exactly one reservation, folio, or restaurant order.');
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw new RuntimeException('Payment amount must be greater than zero.');
            }

            $due = $this->targetOutstanding(
                $data['reservation_id'] ?? null,
                $data['folio_id'] ?? null,
                $data['restaurant_order_id'] ?? null
            );

            if ($amount > $due + 0.009) {
                throw new RuntimeException('Payment amount exceeds the outstanding balance.');
            }

            $payment = Payment::query()->create([
                'idempotency_key' => $data['idempotency_key'],
                'reservation_id' => $data['reservation_id'] ?? null,
                'folio_id' => $data['folio_id'] ?? null,
                'restaurant_order_id' => $data['restaurant_order_id'] ?? null,
                'method' => $data['method'],
                'status' => $data['status'] ?? 'succeeded',
                'amount' => $amount,
                'external_reference' => $data['external_reference'] ?? null,
                'paid_at' => ($data['status'] ?? 'succeeded') === 'succeeded' ? now() : null,
            ]);

            $this->syncTarget($payment);

            return $payment;
        }, 3);
    }

    public function recordProviderCaptured(
        int $reservationId,
        float $amount,
        string $externalReference,
        string $idempotencyKey
    ): Payment {
        return DB::transaction(function () use (
            $reservationId,
            $amount,
            $externalReference,
            $idempotencyKey
        ) {
            $existing = Payment::query()
                ->where('external_reference', $externalReference)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $reservation = Reservation::query()
                ->whereKey($reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = round($amount, 2);
            if ($amount <= 0) {
                throw new RuntimeException('Captured provider payment amount must be greater than zero.');
            }

            $payment = Payment::query()->create([
                'idempotency_key' => $idempotencyKey,
                'reservation_id' => $reservation->id,
                'method' => 'online_gateway',
                'status' => 'succeeded',
                'amount' => $amount,
                'external_reference' => $externalReference,
                'paid_at' => now(),
            ]);

            $this->syncTarget($payment);

            return $payment;
        }, 3);
    }

    public function refund(Payment $payment, array $data): Refund
    {
        $existing = Refund::query()->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing !== null) {
            return $existing;
        }

        $refund = DB::transaction(function () use ($payment, $data) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'succeeded') {
                throw new RuntimeException('Only successful payments can be refunded.');
            }

            $reservedRefunds = (float) $payment->refunds()
                ->whereIn('status', ['pending', 'succeeded'])
                ->sum('amount');
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0 || ($reservedRefunds + $amount) > (float) $payment->amount + 0.009) {
                throw new RuntimeException('Refund amount exceeds the refundable payment balance.');
            }

            $refund = Refund::query()->create([
                'idempotency_key' => $data['idempotency_key'],
                'payment_id' => $payment->id,
                'amount' => $amount,
                'status' => $payment->method === 'online_gateway' ? 'pending' : 'succeeded',
                'reason' => $data['reason'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'refunded_at' => $payment->method === 'online_gateway' ? null : now(),
            ]);

            if ($payment->method !== 'online_gateway') {
                $this->finalizeRefund($payment, $refund);
            }

            return $refund;
        }, 3);

        if ($refund->status !== 'pending') {
            return $refund->fresh();
        }

        $payment = Payment::query()->findOrFail($refund->payment_id);
        if ($payment->external_reference === null) {
            $refund->update(['status' => 'failed']);
            throw new RuntimeException('Online payment reference is missing.');
        }

        try {
            $providerRefund = $this->razorpay->refundPayment(
                $payment->external_reference,
                (int) round((float) $refund->amount * 100),
                $refund->idempotency_key,
                (string) ($refund->reason ?? '')
            );
        } catch (Throwable $exception) {
            // The provider may have accepted the request even if the HTTP response was lost.
            // Keep the local refund pending; a signed refund webhook or receipt reconciliation
            // can safely resolve it without opening the amount for another refund.
            throw new RuntimeException(
                'Online refund status is pending provider reconciliation.',
                previous: $exception
            );
        }

        if (empty($providerRefund['receipt'])) {
            $providerRefund['receipt'] = $refund->idempotency_key;
        }

        return $this->reconcileProviderRefund($providerRefund) ?? $refund->fresh();
    }

    public function reconcileProviderRefund(array $providerRefund): ?Refund
    {
        $providerRefundId = (string) ($providerRefund['id'] ?? '');
        $providerPaymentId = (string) ($providerRefund['payment_id'] ?? '');
        $receipt = (string) ($providerRefund['receipt'] ?? '');
        $amountSubunits = (int) ($providerRefund['amount'] ?? 0);
        $currency = (string) ($providerRefund['currency'] ?? 'INR');
        $status = (string) ($providerRefund['status'] ?? '');

        if (
            $providerRefundId === ''
            || $providerPaymentId === ''
            || $amountSubunits <= 0
            || $currency !== 'INR'
            || ! in_array($status, ['pending', 'processed', 'failed'], true)
        ) {
            throw new RuntimeException('Online payment provider returned an invalid refund response.');
        }

        return DB::transaction(function () use (
            $providerRefundId,
            $providerPaymentId,
            $receipt,
            $amountSubunits,
            $status
        ) {
            $query = Refund::query();
            if ($receipt !== '') {
                $query->where(function ($q) use ($providerRefundId, $receipt) {
                    $q->where('external_reference', $providerRefundId)
                        ->orWhere('idempotency_key', $receipt);
                });
            } else {
                $query->where('external_reference', $providerRefundId);
            }

            $refund = $query->lockForUpdate()->first();
            if ($refund === null) {
                return null;
            }

            $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();

            if (
                $payment->method !== 'online_gateway'
                || $payment->external_reference !== $providerPaymentId
                || (int) round((float) $refund->amount * 100) !== $amountSubunits
            ) {
                throw new RuntimeException('Online refund does not match the local payment ledger.');
            }

            $refund->update([
                'external_reference' => $providerRefundId,
                'status' => $status === 'processed'
                    ? 'succeeded'
                    : ($status === 'failed' ? 'failed' : 'pending'),
                'refunded_at' => $status === 'processed' ? ($refund->refunded_at ?? now()) : null,
            ]);

            if ($status === 'processed') {
                $this->finalizeRefund($payment, $refund->fresh());
            }

            return $refund->fresh();
        }, 3);
    }

    public function reconcilePendingOnlineRefund(Refund $refund): Refund
    {
        if ($refund->status !== 'pending') {
            return $refund;
        }

        $payment = Payment::query()->findOrFail($refund->payment_id);
        if ($payment->method !== 'online_gateway' || $payment->external_reference === null) {
            throw new RuntimeException('Pending refund is not linked to a valid online payment.');
        }

        $providerRefunds = $this->razorpay->fetchRefundsForPayment($payment->external_reference);
        foreach ($providerRefunds as $providerRefund) {
            if (
                ($providerRefund['id'] ?? null) === $refund->external_reference
                || ($providerRefund['receipt'] ?? null) === $refund->idempotency_key
            ) {
                return $this->reconcileProviderRefund($providerRefund) ?? $refund->fresh();
            }
        }

        return $refund->fresh();
    }

    private function targetOutstanding(
        ?int $reservationId,
        ?int $folioId,
        ?int $restaurantOrderId
    ): float {
        if ($reservationId !== null) {
            $reservation = Reservation::query()
                ->whereKey($reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status !== 'confirmed' || $reservation->pricing_status !== 'priced') {
                throw new RuntimeException('Only a confirmed priced reservation can accept a payment.');
            }

            $payments = (float) Payment::query()
                ->where('reservation_id', $reservation->id)
                ->where('status', 'succeeded')
                ->sum('amount');
            $paymentIds = Payment::query()->where('reservation_id', $reservation->id)->pluck('id');
            $refunds = $paymentIds->isEmpty() ? 0.0 : (float) Refund::query()
                ->whereIn('payment_id', $paymentIds)
                ->where('status', 'succeeded')
                ->sum('amount');

            return max(0, round((float) $reservation->total - $payments + $refunds, 2));
        }

        if ($folioId !== null) {
            $folio = Folio::query()->whereKey($folioId)->lockForUpdate()->firstOrFail();
            if ($folio->status !== 'open') {
                throw new RuntimeException('Only an open folio can accept a payment.');
            }

            return max(0, (float) $this->folios->recalculate($folio)->balance);
        }

        $order = RestaurantOrder::query()
            ->whereKey((int) $restaurantOrderId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($order->status === 'cancelled') {
            throw new RuntimeException('A cancelled restaurant order cannot accept payment.');
        }

        $payments = (float) Payment::query()
            ->where('restaurant_order_id', $order->id)
            ->where('status', 'succeeded')
            ->sum('amount');
        $paymentIds = Payment::query()->where('restaurant_order_id', $order->id)->pluck('id');
        $refunds = $paymentIds->isEmpty() ? 0.0 : (float) Refund::query()
            ->whereIn('payment_id', $paymentIds)
            ->where('status', 'succeeded')
            ->sum('amount');

        return max(0, round((float) $order->total - $payments + $refunds, 2));
    }

    private function finalizeRefund(Payment $payment, Refund $refund): void
    {
        $this->syncTarget($payment);
        $this->creditNotes->createForRefund($payment, $refund);
    }

    private function syncTarget(Payment $payment): void
    {
        if ($payment->folio_id !== null) {
            $this->folios->recalculate(Folio::query()->findOrFail($payment->folio_id));
        }

        if ($payment->reservation_id !== null) {
            $reservation = Reservation::query()->findOrFail($payment->reservation_id);
            $payments = (float) Payment::query()
                ->where('reservation_id', $reservation->id)
                ->where('status', 'succeeded')
                ->sum('amount');
            $paymentIds = Payment::query()->where('reservation_id', $reservation->id)->pluck('id');
            $refunds = $paymentIds->isEmpty() ? 0.0 : (float) Refund::query()
                ->whereIn('payment_id', $paymentIds)
                ->where('status', 'succeeded')
                ->sum('amount');
            $net = $payments - $refunds;

            $reservation->update([
                'payment_status' => $net <= 0
                    ? 'unpaid'
                    : ($net + 0.009 >= (float) $reservation->total ? 'paid' : 'partially_paid'),
            ]);
        }

        if ($payment->restaurant_order_id !== null) {
            $order = RestaurantOrder::query()->findOrFail($payment->restaurant_order_id);
            $paid = (float) Payment::query()
                ->where('restaurant_order_id', $order->id)
                ->where('status', 'succeeded')
                ->sum('amount');
            $paymentIds = Payment::query()->where('restaurant_order_id', $order->id)->pluck('id');
            $refunds = $paymentIds->isEmpty() ? 0.0 : (float) Refund::query()
                ->whereIn('payment_id', $paymentIds)
                ->where('status', 'succeeded')
                ->sum('amount');
            $net = $paid - $refunds;

            $paymentStatus = $net <= 0
                ? 'unpaid'
                : ($net + 0.009 >= (float) $order->total ? 'paid' : 'partially_paid');

            $order->update(['payment_status' => $paymentStatus]);

            if (
                $order->order_type === 'dine_in'
                && $order->status === 'served'
                && $paymentStatus === 'paid'
                && $order->restaurant_table_id !== null
            ) {
                RestaurantTable::query()
                    ->whereKey($order->restaurant_table_id)
                    ->update(['status' => 'available']);
            }
        }
    }
}
