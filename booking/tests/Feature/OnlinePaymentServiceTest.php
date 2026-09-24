<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentGatewayOrder;
use App\Models\Refund;
use App\Models\Reservation;
use App\Services\OnlinePaymentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnlinePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.razorpay.key_id', 'rzp_test_key');
        config()->set('services.razorpay.key_secret', 'test-secret');
        config()->set('services.razorpay.webhook_secret', 'webhook-secret');
        config()->set('services.razorpay.api_base', 'https://api.razorpay.com/v1');
    }

    private function reservation(string $suffix = '1'): Reservation
    {
        return Reservation::query()->create([
            'booking_number' => 'HV-ONLINE-'.$suffix,
            'public_token' => (string) Str::uuid(),
            'check_in_date' => today()->addDay(),
            'check_out_date' => today()->addDays(2),
            'status' => 'confirmed',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
        ]);
    }

    public function test_checkout_signature_and_captured_payment_are_verified_before_posting(): void
    {
        $reservation = $this->reservation('1');

        Http::fake(function (HttpRequest $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.razorpay.com/v1/orders') {
                return Http::response([
                    'id' => 'order_verified',
                    'amount' => 100000,
                    'currency' => 'INR',
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.razorpay.com/v1/payments/pay_verified') {
                return Http::response([
                    'id' => 'pay_verified',
                    'order_id' => 'order_verified',
                    'amount' => 100000,
                    'currency' => 'INR',
                    'status' => 'captured',
                ], 200);
            }

            return Http::response([], 404);
        });

        $service = app(OnlinePaymentService::class);
        $order = $service->createOrder($reservation);
        $signature = hash_hmac('sha256', $order->provider_order_id.'|pay_verified', 'test-secret');

        $payment = $service->verifyCheckout(
            $reservation,
            $order->provider_order_id,
            'pay_verified',
            $signature
        );

        $this->assertSame('online_gateway', $payment->method);
        $this->assertSame('pay_verified', $payment->external_reference);
        $this->assertSame('paid', $reservation->fresh()->payment_status);
        $this->assertDatabaseHas('payment_gateway_orders', [
            'id' => $order->id,
            'provider_payment_id' => 'pay_verified',
            'status' => 'paid',
        ]);
    }

    public function test_online_refund_waits_for_provider_processing_webhook_before_accounting_success(): void
    {
        $reservation = $this->reservation('2');

        $payment = app(PaymentService::class)->record([
            'idempotency_key' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'method' => 'online_gateway',
            'amount' => 1000,
            'external_reference' => 'pay_refund_test',
        ]);

        $refundKey = (string) Str::uuid();

        Http::fake([
            'https://api.razorpay.com/v1/payments/pay_refund_test/refund' => Http::response([
                'id' => 'rfnd_pending',
                'entity' => 'refund',
                'payment_id' => 'pay_refund_test',
                'amount' => 25000,
                'currency' => 'INR',
                'receipt' => $refundKey,
                'status' => 'pending',
            ], 200),
        ]);

        $refund = app(PaymentService::class)->refund($payment, [
            'idempotency_key' => $refundKey,
            'amount' => 250,
            'reason' => 'Guest adjustment',
        ]);

        $this->assertSame('pending', $refund->status);
        $this->assertSame('paid', $reservation->fresh()->payment_status);

        $payload = json_encode([
            'event' => 'refund.processed',
            'payload' => [
                'refund' => [
                    'entity' => [
                        'id' => 'rfnd_pending',
                        'payment_id' => 'pay_refund_test',
                        'amount' => 25000,
                        'currency' => 'INR',
                        'receipt' => $refundKey,
                        'status' => 'processed',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        app(OnlinePaymentService::class)->processWebhook(
            $payload,
            hash_hmac('sha256', $payload, 'webhook-secret')
        );

        $this->assertSame('succeeded', $refund->fresh()->status);
        $this->assertSame('partially_paid', $reservation->fresh()->payment_status);
    }

    public function test_stale_gateway_order_capture_is_still_ledgered_for_reconciliation(): void
    {
        $reservation = $this->reservation('3');

        Http::fake(function (HttpRequest $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.razorpay.com/v1/orders') {
                return Http::response([
                    'id' => 'order_stale',
                    'amount' => 100000,
                    'currency' => 'INR',
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.razorpay.com/v1/payments/pay_stale') {
                return Http::response([
                    'id' => 'pay_stale',
                    'order_id' => 'order_stale',
                    'amount' => 100000,
                    'currency' => 'INR',
                    'status' => 'captured',
                ], 200);
            }

            return Http::response([], 404);
        });

        $service = app(OnlinePaymentService::class);
        $order = $service->createOrder($reservation);
        $order->update(['status' => 'stale']);

        $reservation->update(['subtotal' => 800, 'total' => 800]);

        $signature = hash_hmac('sha256', 'order_stale|pay_stale', 'test-secret');
        $payment = $service->verifyCheckout(
            $reservation->fresh(),
            'order_stale',
            'pay_stale',
            $signature
        );

        $this->assertSame('1000.00', $payment->amount);
        $this->assertSame('pay_stale', $payment->external_reference);
        $this->assertSame('paid_stale', $order->fresh()->status);
        $this->assertSame('paid', $reservation->fresh()->payment_status);
    }

    public function test_webhook_route_accepts_valid_signed_server_to_server_request_without_csrf_token(): void
    {
        $payload = json_encode(['event' => 'test.ping'], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'webhook-secret');

        $response = $this->call(
            'POST',
            '/payments/razorpay/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
            ],
            $payload
        );

        $response->assertOk();
        $this->assertDatabaseHas('payment_webhook_events', [
            'event_hash' => hash('sha256', $payload),
            'event_name' => 'test.ping',
        ]);
    }

    public function test_webhook_is_idempotent_for_same_raw_event(): void
    {
        $payload = json_encode(['event' => 'test.duplicate'], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'webhook-secret');
        $service = app(OnlinePaymentService::class);

        $service->processWebhook($payload, $signature);
        $service->processWebhook($payload, $signature);

        $this->assertSame(1, \App\Models\PaymentWebhookEvent::query()->count());
    }
}
