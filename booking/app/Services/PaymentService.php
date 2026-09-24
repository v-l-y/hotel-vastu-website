<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
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

            if ($amount > ($due + 0.009)) {
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

    public function refund(Payment $payment, array $data): Refund
    {
        $refund = DB::transaction(function () use ($payment, $data) {
            $existing = Refund::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                return $existing;
            }

            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'succeeded') {
                throw new RuntimeException('Only successful payments can be refunded.');
            }

            $reservedRefunds = (float) $payment->refunds()
                ->whereIn('status', ['pending', 'succeeded'])
                ->sum('amount');
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0 || ($reservedRefunds + $amount) > ((float) $payment->amount + 0.009)) {
                throw new RuntimeException('Refund amount exceeds the refundable payment balance.');
            }

            return Refund::query()->create([
                'idempotency_key' => $data['idempotency_key'],
                'payment_id' => $payment->id,
                'amount' => $amount,
                'status' => $payment->method === 'online_gateway' ? 'pending' : 'succeeded',
                'reason' => $data['reason'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'refunded_at' => $payment->method === 'online_gateway' ? null : now(),
            ]);
        }, 3);

        if ($refund->status === 'failed') {
            throw new RuntimeException('A previous refund attempt with this idempotency key failed.');
        }

        if ($refund->status === 'pending') {
            $payment = Payment::query()->findOrFail($refund->payment_id);

            if ($payment->method !== 'online_gateway' || empty($payment->external_reference)) {
                $refund->update(['status' => 'failed']);
                throw new RuntimeException('Online refund cannot be sent because the provider payment reference is missing.');
            }

            try {
                $providerRefund = $this->razorpay->refundPayment(
                    $payment->external_reference,
                    (int) round((float) $refund->amount * 100),
                    (string) ($refund->reason ?? '')
                );
            } catch (Throwable $exception) {
                $refund->update(['status' => 'failed']);

                throw new RuntimeException('Online refund failed at the payment provider.', previous: $exception);
            }

            $providerRefundId = (string) ($providerRefund['id'] ?? '');
            $providerAmount = (int) ($providerRefund['amount'] ?? 0);
            $providerStatus = (string) ($providerRefund['status'] ?? '');

            if (
                $providerRefundId === ''
                || $providerAmount !== (int) round((float) $refund->amount * 100)
                || ! in_array($providerStatus, ['processed', 'pending'], true)
            ) {
                $refund->update(['status' => 'failed']);
                throw new RuntimeException('Online refund provider returned an invalid response.');
            }

            $refund->update([
                'status' => 'succeeded',
                'external_reference' => $providerRefundId,
                'refunded_at' => now(),
            ]);
        }

        $payment = Payment::query()->findOrFail($refund->payment_id);
        $this->syncTarget($payment);
        $this->creditNotes->createForRefund($payment, $refund->fresh());

        return $refund->fresh();
    }

    private function targetOutstanding(
        ?int $reservationId,
        ?int $folioId,
        ?int $restaurantOrderId
    ): float {
        if ($reservationId !== null) {
            $reservation = Reservation::query()->findOrFail($reservationId);
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
            $folio = Folio::query()->findOrFail($folioId);
            if ($folio->status !== 'open') {
                throw new RuntimeException('Only an open folio can accept a payment.');
            }

            $folio = $this->folios->recalculate($folio);

            return max(0, (float) $folio->balance);
        }

        $order = RestaurantOrder::query()->findOrFail((int) $restaurantOrderId);
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

    private function syncTarget(Payment $payment): void
    {
        if ($payment->folio_id !== null) {
            $folio = Folio::query()->findOrFail($payment->folio_id);
            $this->folios->recalculate($folio);
        }

        if ($payment->reservation_id !== null) {
            $reservation = Reservation::query()->findOrFail($payment->reservation_id);
            if ($reservation->pricing_status !== 'priced') {
                throw new RuntimeException('Reservation must be priced before payment.');
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
            $order->update([
                'payment_status' => $net <= 0
                    ? 'unpaid'
                    : ($net + 0.009 >= (float) $order->total ? 'paid' : 'partially_paid'),
            ]);
        }
    }
}
