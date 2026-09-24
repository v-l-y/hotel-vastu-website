<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\PaymentGatewayOrder;
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
                $sameRequest =
                    (int) ($existing->reservation_id ?? 0) === (int) ($data['reservation_id'] ?? 0)
                    && (int) ($existing->folio_id ?? 0) === (int) ($data['folio_id'] ?? 0)
                    && (int) ($existing->restaurant_order_id ?? 0) === (int) ($data['restaurant_order_id'] ?? 0)
                    && $existing->method === $data['method']
                    && abs((float) $existing->amount - round((float) $data['amount'], 2)) < 0.009
                    && (string) ($existing->external_reference ?? '') === (string) ($data['external_reference'] ?? '');

                if (! $sameRequest) {
                    throw new RuntimeException('Idempotency key was already used for a different payment request.');
                }

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

    public function reservationNetPaid(int $reservationId): float
    {
        $payments = (float) Payment::query()
            ->where('reservation_id', $reservationId)
            ->where('status', 'succeeded')
            ->sum('amount');

        $paymentIds = Payment::query()->where('reservation_id', $reservationId)->pluck('id');
        $refunds = $paymentIds->isEmpty() ? 0.0 : (float) Refund::query()
            ->whereIn('payment_id', $paymentIds)
            ->where('status', 'succeeded')
            ->sum('amount');

        return round($payments - $refunds, 2);
    }

    public function reservationOutstanding(int $reservationId): float
    {
        $reservation = Reservation::query()->findOrFail($reservationId);

        return max(0, round((float) $reservation->total - $this->reservationNetPaid($reservationId), 2));
    }

    public function reservationOverpaid(int $reservationId): float
    {
        $reservation = Reservation::query()->findOrFail($reservationId);

        return max(0, round($this->reservationNetPaid($reservationId) - (float) $reservation->total, 2));
    }

    public function restaurantOutstanding(int $restaurantOrderId): float
    {
        $order = RestaurantOrder::query()->findOrFail($restaurantOrderId);
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

    public function syncReservationPaymentState(
        Reservation $reservation,
        bool $staleOpenGatewayOrders = true
    ): Reservation {
        if ($staleOpenGatewayOrders) {
            $this->staleOpenGatewayOrders($reservation->id);
        }

        $net = $this->reservationNetPaid($reservation->id);
        $total = (float) $reservation->total;

        $reservation->update([
            'payment_status' => $total <= 0.009
                ? 'paid'
                : ($net <= 0
                    ? 'unpaid'
                    : ($net > $total + 0.009
                        ? 'overpaid'
                        : ($net + 0.009 >= $total ? 'paid' : 'partially_paid'))),
        ]);

        return $reservation->fresh();
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

            $openFolio = $reservation->status === 'checked_in'
                ? Folio::query()
                    ->where('reservation_id', $reservation->id)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first()
                : null;

            $payment = Payment::query()->create([
                'idempotency_key' => $idempotencyKey,
                'reservation_id' => $openFolio === null ? $reservation->id : null,
                'folio_id' => $openFolio?->id,
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
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Refund reason is required.');
        }

        $existing = Refund::query()->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing !== null) {
            $sameRequest =
                (int) $existing->payment_id === (int) $payment->id
                && abs((float) $existing->amount - round((float) $data['amount'], 2)) < 0.009
                && (string) ($existing->reason ?? '') === (string) ($data['reason'] ?? '');

            if (! $sameRequest) {
                throw new RuntimeException('Idempotency key was already used for a different refund request.');
            }

            return $existing;
        }

        $refund = DB::transaction(function () use ($payment, $data, $reason) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'succeeded') {
                throw new RuntimeException('Only successful payments can be refunded.');
            }

            $reservedRefunds = (float) $payment->refunds()
                ->whereIn('status', ['pending', 'pending_manual', 'succeeded'])
                ->sum('amount');
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0 || ($reservedRefunds + $amount) > (float) $payment->amount + 0.009) {
                throw new RuntimeException('Refund amount exceeds the refundable payment balance.');
            }

            $status = $payment->method === 'online_gateway'
                ? 'pending'
                : ($payment->method === 'cash' ? 'succeeded' : 'pending_manual');

            $refund = Refund::query()->create([
                'idempotency_key' => $data['idempotency_key'],
                'payment_id' => $payment->id,
                'amount' => $amount,
                'status' => $status,
                'reason' => $reason,
                'external_reference' => null,
                'refunded_at' => $status === 'succeeded' ? now() : null,
            ]);

            if ($status === 'succeeded') {
                $this->finalizeRefund($payment, $refund);
            }

            return $refund;
        }, 3);

        if ($refund->status === 'pending_manual') {
            return $refund->fresh();
        }

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

    public function confirmManualRefund(Refund $refund, string $externalReference): Refund
    {
        $externalReference = trim($externalReference);
        if ($externalReference === '') {
            throw new RuntimeException('External refund reference is required.');
        }

        return DB::transaction(function () use ($refund, $externalReference) {
            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();

            if ($refund->status !== 'pending_manual') {
                throw new RuntimeException('Only a pending manual refund can be confirmed.');
            }

            if (! in_array($payment->method, ['upi', 'card', 'bank_transfer'], true)) {
                throw new RuntimeException('This payment does not require manual refund confirmation.');
            }

            $refund->update([
                'status' => 'succeeded',
                'external_reference' => $externalReference,
                'refunded_at' => now(),
            ]);

            $this->finalizeRefund($payment, $refund->fresh());

            return $refund->fresh();
        }, 3);
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

        if ($order->status !== 'served') {
            throw new RuntimeException('Only a served restaurant order can accept direct payment.');
        }

        if ($order->order_type === 'room_service') {
            throw new RuntimeException('Room-service orders must be settled through the guest folio.');
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

        if ($payment->reservation_id !== null) {
            $this->staleOpenGatewayOrders($payment->reservation_id);
        }

        $this->creditNotes->createForRefund($payment, $refund);
    }

    private function staleOpenGatewayOrders(int $reservationId): void
    {
        PaymentGatewayOrder::query()
            ->where('reservation_id', $reservationId)
            ->where('status', 'created')
            ->update(['status' => 'stale']);
    }

    private function syncTarget(Payment $payment): void
    {
        if ($payment->folio_id !== null) {
            $this->folios->recalculate(Folio::query()->findOrFail($payment->folio_id));
        }

        if ($payment->reservation_id !== null) {
            $reservation = Reservation::query()->findOrFail($payment->reservation_id);
            $this->syncReservationPaymentState(
                $reservation,
                $payment->method !== 'online_gateway'
            );
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
