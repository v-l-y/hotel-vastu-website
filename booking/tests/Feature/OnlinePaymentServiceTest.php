<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\OnlinePaymentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnlinePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key_id' => 'rzp_test_key',
            'services.razorpay.key_secret' => 'test_secret',
            'services.razorpay.webhook_secret' => 'webhook_secret',
            'services.razorpay.api_base' => 'https://api.razorpay.com/v1',
        ]);
    }

    private function reservation(string $suffix): Reservation
    {
        return Reservation::query()->create([
            'booking_number'=>'HV-ONLINE-'.$suffix,
            'public_token'=>sprintf('12121212-1212-4212-8212-%012d', (int) $suffix),
            'check_in_date'=>today()->addDay(),
            'check_out_date'=>today()->addDays(2),
            'status'=>'confirmed',
            'pricing_status'=>'priced',
            'payment_status'=>'unpaid',
            'subtotal'=>1000,
            'tax'=>0,
            'total'=>1000,
        ]);
    }

    public function test_checkout_payment_is_verified_against_captured_provider_payment_and_can_be_refunded_at_provider(): void
    {
        $reservation = $this->reservation('1');

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response([
                'id'=>'order_test_1','amount'=>100000,'currency'=>'INR',
            ], 200),
            'https://api.razorpay.com/v1/payments/pay_test_1' => Http::response([
                'id'=>'pay_test_1','order_id'=>'order_test_1','amount'=>100000,
                'currency'=>'INR','status'=>'captured',
            ], 200),
            'https://api.razorpay.com/v1/payments/pay_test_1/refund' => Http::response([
                'id'=>'rfnd_test_1','amount'=>25000,'status'=>'processed',
            ], 200),
        ]);

        $service = app(OnlinePaymentService::class);
        $order = $service->createOrder($reservation);
        $signature = hash_hmac('sha256', $order->provider_order_id.'|pay_test_1', 'test_secret');

        $payment = $service->verifyCheckout(
            $reservation,
            $order->provider_order_id,
            'pay_test_1',
            $signature
        );

        $this->assertSame('online_gateway', $payment->method);
        $this->assertSame('paid', $reservation->fresh()->payment_status);
        $this->assertSame('paid', $order->fresh()->status);

        $refund = app(PaymentService::class)->refund($payment, [
            'idempotency_key'=>'13131313-1313-4313-8313-131313131313',
            'amount'=>250,
            'reason'=>'Guest cancellation adjustment',
        ]);

        $this->assertSame('succeeded', $refund->status);
        $this->assertSame('rfnd_test_1', $refund->external_reference);
        $this->assertSame('partially_paid', $reservation->fresh()->payment_status);

        Http::assertSent(fn ($request) =>
            $request->url()==='https://api.razorpay.com/v1/payments/pay_test_1/refund'
            && (int) $request['amount']===25000
        );
    }

    public function test_signed_captured_webhook_records_payment_once(): void
    {
        $reservation = $this->reservation('2');

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response([
                'id'=>'order_webhook','amount'=>100000,'currency'=>'INR',
            ], 200),
        ]);

        $service = app(OnlinePaymentService::class);
        $order = $service->createOrder($reservation);

        $body = json_encode([
            'event'=>'payment.captured',
            'payload'=>[
                'payment'=>[
                    'entity'=>[
                        'id'=>'pay_webhook',
                        'order_id'=>$order->provider_order_id,
                        'amount'=>100000,
                        'currency'=>'INR',
                        'status'=>'captured',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'webhook_secret');

        $service->processWebhook($body, $signature);
        $service->processWebhook($body, $signature);

        $this->assertSame(1, Payment::query()->where('external_reference','pay_webhook')->count());
        $this->assertSame(1, \App\Models\PaymentWebhookEvent::query()->count());
        $this->assertSame('paid', $reservation->fresh()->payment_status);
    }
}
