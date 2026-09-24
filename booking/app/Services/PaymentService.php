<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentService
{
    public function __construct(private FolioService $folios)
    {
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

            $this->syncTarget($payment);

            return $payment;
        }, 3);
    }

    public function refund(Payment $payment, array $data): Refund
    {
        return DB::transaction(function () use ($payment, $data) {
            $existing = Refund::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                return $existing;
            }

            if ($payment->status !== 'succeeded') {
                throw new RuntimeException('Only successful payments can be refunded.');
            }

            $alreadyRefunded = (float) $payment->refunds()->where('status', 'succeeded')->sum('amount');
            $amount = (float) $data['amount'];

            if ($amount <= 0 || ($alreadyRefunded + $amount) > (float) $payment->amount) {
                throw new RuntimeException('Refund amount exceeds the refundable payment balance.');
            }

            $refund = Refund::query()->create([
                'idempotency_key' => $data['idempotency_key'],
                'payment_id' => $payment->id,
                'amount' => $amount,
                'status' => 'succeeded',
                'reason' => $data['reason'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'refunded_at' => now(),
            ]);

            $this->syncTarget($payment->fresh());

            return $refund;
        }, 3);
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
