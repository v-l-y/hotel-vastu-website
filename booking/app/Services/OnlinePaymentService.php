<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentGatewayOrder;
use App\Models\PaymentWebhookEvent;
use App\Models\Refund;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OnlinePaymentService
{
    public function __construct(
        private RazorpayClient $razorpay,
        private PaymentService $payments
    ) {
    }

    public function enabled(): bool
    {
        return $this->razorpay->enabled();
    }

    public function keyId(): string
    {
        return $this->razorpay->keyId();
    }

    public function createOrder(Reservation $reservation): PaymentGatewayOrder
    {
        $reservation = Reservation::query()->whereKey($reservation->id)->firstOrFail();

        if (! $this->enabled()) {
            throw new RuntimeException('Online payment gateway is not configured.');
        }

        if ($reservation->status !== 'confirmed' || $reservation->pricing_status !== 'priced') {
            throw new RuntimeException('Only confirmed priced reservations can be paid online.');
        }

        $due = round((float) $reservation->total - $this->reservationNetPaid($reservation->id), 2);
        if ($due <= 0) {
            throw new RuntimeException('This booking is already fully paid.');
        }

        $amountSubunits = (int) round($due * 100);
        $existing = PaymentGatewayOrder::query()
            ->where('provider', 'razorpay')
            ->where('reservation_id', $reservation->id)
            ->where('amount_subunits', $amountSubunits)
            ->where('status', 'created')
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        PaymentGatewayOrder::query()
            ->where('provider', 'razorpay')
            ->where('reservation_id', $reservation->id)
            ->where('status', 'created')
            ->update(['status' => 'stale']);

        $providerOrder = $this->razorpay->createOrder(
            $amountSubunits,
            $reservation->booking_number,
            ['booking_number' => $reservation->booking_number]
        );

        if (
            empty($providerOrder['id'])
            || (int) ($providerOrder['amount'] ?? 0) !== $amountSubunits
            || ($providerOrder['currency'] ?? '') !== 'INR'
        ) {
            throw new RuntimeException('Online payment provider returned an invalid order.');
        }

        return PaymentGatewayOrder::query()->create([
            'provider' => 'razorpay',
            'reservation_id' => $reservation->id,
            'provider_order_id' => $providerOrder['id'],
            'amount_subunits' => $amountSubunits,
            'currency' => 'INR',
            'status' => 'created',
        ]);
    }

    public function verifyCheckout(
        Reservation $reservation,
        string $providerOrderId,
        string $providerPaymentId,
        string $signature
    ): Payment {
        $gatewayOrder = PaymentGatewayOrder::query()
            ->where('provider', 'razorpay')
            ->where('reservation_id', $reservation->id)
            ->where('provider_order_id', $providerOrderId)
            ->firstOrFail();

        if (! in_array($gatewayOrder->status, ['created', 'paid'], true)) {
            throw new RuntimeException('This online payment order is no longer valid.');
        }

        if (! $this->razorpay->verifyCheckoutSignature(
            $gatewayOrder->provider_order_id,
            $providerPaymentId,
            $signature
        )) {
            throw new RuntimeException('Online payment signature verification failed.');
        }

        $providerPayment = $this->razorpay->fetchPayment($providerPaymentId);

        if (
            ($providerPayment['status'] ?? '') !== 'captured'
            || ($providerPayment['order_id'] ?? '') !== $gatewayOrder->provider_order_id
            || (int) ($providerPayment['amount'] ?? 0) !== (int) $gatewayOrder->amount_subunits
            || ($providerPayment['currency'] ?? '') !== 'INR'
        ) {
            throw new RuntimeException('Online payment is not captured or does not match this booking.');
        }

        return $this->recordCapturedPayment(
            $gatewayOrder,
            $providerPaymentId,
            (int) $providerPayment['amount']
        );
    }

    public function processWebhook(string $rawBody, string $signature): void
    {
        if (! $this->razorpay->verifyWebhookSignature($rawBody, $signature)) {
            throw new RuntimeException('Invalid online payment webhook signature.');
        }

        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        $eventHash = hash('sha256', $rawBody);

        DB::transaction(function () use ($payload, $eventHash) {
            $event = PaymentWebhookEvent::query()->firstOrCreate(
                ['event_hash' => $eventHash],
                [
                    'provider' => 'razorpay',
                    'event_name' => (string) ($payload['event'] ?? 'unknown'),
                    'processed_at' => now(),
                ]
            );

            if (! $event->wasRecentlyCreated) {
                return;
            }

            $eventName = (string) ($payload['event'] ?? '');
            $providerOrderId = null;
            $providerPaymentId = null;

            if ($eventName === 'payment.captured') {
                $payment = $payload['payload']['payment']['entity'] ?? [];
                $providerOrderId = $payment['order_id'] ?? null;
                $providerPaymentId = $payment['id'] ?? null;

                if (
                    ! is_string($providerOrderId)
                    || ! is_string($providerPaymentId)
                    || ($payment['status'] ?? '') !== 'captured'
                    || ($payment['currency'] ?? '') !== 'INR'
                ) {
                    throw new RuntimeException('Malformed captured-payment webhook.');
                }

                $gatewayOrder = PaymentGatewayOrder::query()
                    ->where('provider', 'razorpay')
                    ->where('provider_order_id', $providerOrderId)
                    ->lockForUpdate()
                    ->first();

                if ($gatewayOrder !== null) {
                    if ((int) ($payment['amount'] ?? 0) !== (int) $gatewayOrder->amount_subunits) {
                        throw new RuntimeException('Webhook payment amount does not match booking order.');
                    }
                    if ($gatewayOrder->status === 'stale') {
                        throw new RuntimeException('Captured payment belongs to a stale booking order and requires manual reconciliation.');
                    }

                    $this->recordCapturedPayment(
                        $gatewayOrder,
                        $providerPaymentId,
                        (int) $payment['amount']
                    );
                }
            } elseif (in_array($eventName, ['refund.created', 'refund.processed', 'refund.failed'], true)) {
                $providerRefund = $payload['payload']['refund']['entity'] ?? [];
                $providerPaymentId = $providerRefund['payment_id'] ?? null;

                if (! is_array($providerRefund) || ! is_string($providerPaymentId)) {
                    throw new RuntimeException('Malformed refund webhook.');
                }

                $this->payments->reconcileProviderRefund($providerRefund);
            }

            $event->update([
                'provider_order_id' => $providerOrderId,
                'provider_payment_id' => $providerPaymentId,
                'processed_at' => now(),
            ]);
        }, 3);
    }

    private function recordCapturedPayment(
        PaymentGatewayOrder $gatewayOrder,
        string $providerPaymentId,
        int $amountSubunits
    ): Payment {
        $existing = Payment::query()->where('external_reference', $providerPaymentId)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($gatewayOrder, $providerPaymentId, $amountSubunits) {
            $lockedOrder = PaymentGatewayOrder::query()
                ->whereKey($gatewayOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = Payment::query()->where('external_reference', $providerPaymentId)->first();
            if ($existing !== null) {
                return $existing;
            }

            if (! in_array($lockedOrder->status, ['created', 'paid'], true)) {
                throw new RuntimeException('Online payment order is not eligible for capture posting.');
            }

            $payment = $this->payments->record([
                'idempotency_key' => (string) Str::uuid(),
                'reservation_id' => $lockedOrder->reservation_id,
                'method' => 'online_gateway',
                'status' => 'succeeded',
                'amount' => round($amountSubunits / 100, 2),
                'external_reference' => $providerPaymentId,
            ]);

            $lockedOrder->update([
                'provider_payment_id' => $providerPaymentId,
                'status' => 'paid',
            ]);

            return $payment;
        }, 3);
    }

    private function reservationNetPaid(int $reservationId): float
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
}
