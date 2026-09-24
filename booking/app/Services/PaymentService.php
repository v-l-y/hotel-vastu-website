<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\PaymentGatewayOrder;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

            if ((float) $data['amount'] <= 0) {
                throw new RuntimeException('Payment amount must be greater than zero.');
            }

            $payment = Payment::query()->create([
                'idempotency_key' => $data['idempotency_key'],
                'reservation_id' => $data['reservation_id'] ?? null,
                'folio_id' => $data['folio_id'] ?? null,
                'restaurant_order_id' => $data['restaurant_order_id'] ?? null,
                'method' => $data['method'],
                'status' => $data['status'] ?? 'succeeded',
                'amount' => $data['amount'],
                'external_reference' => $data['external_reference'] ?? null,
                'paid_at' => ($data['status'] ?? 'succeeded') === 'succeeded' ? now() : null,
            ]);

            if ($payment->reservation_id !== null) {
                PaymentGatewayOrder::query()
                    ->where('reservation_id', $payment->reservation_id)
                    ->where('status', 'created')
                    ->update(['status' => 'stale']);
            }

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

        $validation = DB::transaction(function () use ($payment, $data) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'succeeded') {
                throw new RuntimeException('Only successful payments can be refunded.');
            }

            $alreadyRefunded = (float) $lockedPayment->refunds()
                ->whereIn('status', ['pending', 'succeeded'])
                ->sum('amount');
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0 || ($alreadyRefunded + $amount) > (float) $lockedPayment->amount + 0.009) {
                throw new RuntimeException('Refund amount exceeds the refundable payment balance.');
            }

            return [
                'payment' => $lockedPayment,
                'amount' => $amount,
            ];
        }, 3);

        /** @var Payment $lockedPayment */
        $lockedPayment = $validation['payment'];
        $amount = $validation['amount'];

        if ($lockedPayment->method === 'online_gateway') {
            if ($lockedPayment->external_reference === null) {
                throw new RuntimeException('Online payment reference is missing.');
            }

            $providerRefund = $this->razorpay->refundPayment(
                $lockedPayment->external_reference,
                (int) round($amount * 100),
                (string) ($data['reason'] ?? '')
            );

            if (
                empty($providerRefund['id'])
                || ($providerRefund['payment_id'] ?? '') !== $lockedPayment->external_reference
                || (int) ($providerRefund['amount'] ?? 0) !== (int) round($amount * 100)
                || ($providerRefund['currency'] ?? 'INR') !== 'INR'
                || ! in_array(($providerRefund['status'] ?? ''), ['pending', 'processed'], true)
            ) {
                throw new RuntimeException('Online payment provider returned an invalid refund response.');
            }

            return DB::transaction(function () use ($lockedPayment, $data, $amount, $providerRefund) {
                $existing = Refund::query()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing !== null) {
                    return $existing;
                }

                $processed = ($providerRefund['status'] ?? '') === 'processed';

                $refund = Refund::query()->create([
                    'idempotency_key' => $data['idempotency_key'],
                    'payment_id' => $lockedPayment->id,
                    'amount' => $amount,
                    'status' => $processed ? 'succeeded' : 'pending',
                    'reason' => $data['reason'] ?? null,
                    'external_reference' => $providerRefund['id'],
                    'refunded_at' => $processed ? now() : null,
                ]);

                if ($processed) {
                    $this->finalizeRefund($lockedPayment->fresh(), $refund);
                }

                return $refund;
            }, 3);
        }

        return DB::transaction(function () use ($lockedPayment, $data, $amount) {
            $existing = Refund::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                return $existing;
            }

            $refund = Refund::query()->create([
                'idempotency_key' => $data['idempotency_key'],
                'payment_id' => $lockedPayment->id,
                'amount' => $amount,
                'status' => 'succeeded',
                'reason' => $data['reason'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'refunded_at' => now(),
            ]);

            $this->finalizeRefund($lockedPayment->fresh(), $refund);

            return $refund;
        }, 3);
    }

    public function markProviderRefundProcessed(string $providerRefundId): ?Refund
    {
        return DB::transaction(function () use ($providerRefundId) {
            $refund = Refund::query()
                ->where('external_reference', $providerRefundId)
                ->lockForUpdate()
                ->first();

            if ($refund === null) {
                return null;
            }

            if ($refund->status === 'succeeded') {
                return $refund;
            }

            $refund->update([
                'status' => 'succeeded',
                'refunded_at' => now(),
            ]);

            $this->finalizeRefund($refund->payment()->firstOrFail(), $refund->fresh());

            return $refund->fresh();
        }, 3);
    }

    public function markProviderRefundFailed(string $providerRefundId): ?Refund
    {
        return DB::transaction(function () use ($providerRefundId) {
            $refund = Refund::query()
                ->where('external_reference', $providerRefundId)
                ->lockForUpdate()
                ->first();

            if ($refund === null || $refund->status === 'succeeded') {
                return $refund;
            }

            $refund->update(['status' => 'failed']);

            return $refund->fresh();
        }, 3);
    }

    private function finalizeRefund(Payment $payment, Refund $refund): void
    {
        $this->syncTarget($payment);
        $this->creditNotes->createForRefund($payment, $refund);
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
