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

        if ($reservation->status !== 'confirmed' || $reservation->pricing_status !== 'priced') {
            throw new RuntimeException('Only confirmed priced reservations can be paid online.');
        }

        $netPaid = $this->reservationNetPaid($reservation->id);
        $due = round((float) $reservation->total - $netPaid, 2);
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
        if (! $this->razorpay->verifyCheckoutSignature($providerOrderId, $providerPaymentId, $signature)) {
            throw new RuntimeException('Online payment signature verification failed.');
        }

        $gatewayOrder = PaymentGatewayOrder::query()
            ->where('provider', 'razorpay')
            ->where('reservation_id', $reservation->id)
            ->where('provider_order_id', $providerOrderId)
            ->firstOrFail();

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

        $eventHash = hash('sha256', $rawBody);
        if (PaymentWebhookEvent::query()->where('event_hash', $eventHash)->exists()) {
            return;
        }

        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
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
                ->first();

            if ($gatewayOrder !== null) {
                if ((int) ($payment['amount'] ?? 0) !== (int) $gatewayOrder->amount_subunits) {
                    throw new RuntimeException('Webhook payment amount does not match booking order.');
                }

                $this->recordCapturedPayment(
                    $gatewayOrder,
                    $providerPaymentId,
                    (int) $payment['amount']
                );
            }
        }

        PaymentWebhookEvent::query()->create([
            'provider' => 'razorpay',
            'event_hash' => $eventHash,
            'event_name' => $eventName !== '' ? $eventName : 'unknown',
            'provider_order_id' => $providerOrderId,
            'provider_payment_id' => $providerPaymentId,
            'processed_at' => now(),
        ]);
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
