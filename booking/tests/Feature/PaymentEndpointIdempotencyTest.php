<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentEndpointIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): AdminUser
    {
        return AdminUser::query()->create([
            'name' => 'Payment Admin',
            'email' => 'payment-idempotency@example.com',
            'password_hash' => password_hash('test-password-123', PASSWORD_DEFAULT),
            'role' => 'administrator',
            'is_active' => true,
        ]);
    }

    private function reservation(): Reservation
    {
        return Reservation::query()->create([
            'booking_number' => 'HV-ENDPOINT-IDEMPOTENT',
            'public_token' => '34343434-3434-4343-8343-343434343434',
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

    public function test_duplicate_partial_payment_post_uses_one_ledger_entry(): void
    {
        $admin = $this->administrator();
        $reservation = $this->reservation();
        $idempotencyKey = '12121212-1212-4212-8212-121212121212';

        $payload = [
            'idempotency_key' => $idempotencyKey,
            'target_type' => 'reservation',
            'target_id' => $reservation->id,
            'method' => 'cash',
            'amount' => 100,
        ];

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/payments', $payload)
            ->assertRedirect();

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/payments', $payload)
            ->assertRedirect();

        $this->assertSame(1, Payment::query()
            ->where('idempotency_key', $idempotencyKey)
            ->count());
        $this->assertSame('partially_paid', $reservation->fresh()->payment_status);
    }

    public function test_duplicate_refund_post_uses_one_refund_entry(): void
    {
        $admin = $this->administrator();
        $reservation = $this->reservation();

        $payment = app(PaymentService::class)->record([
            'idempotency_key' => '23232323-2323-4232-8232-232323232323',
            'reservation_id' => $reservation->id,
            'method' => 'cash',
            'amount' => 500,
        ]);

        $idempotencyKey = '45454545-4545-4454-8454-454545454545';
        $payload = [
            'idempotency_key' => $idempotencyKey,
            'amount' => 100,
            'refund_type' => 'overpayment',
            'reason' => 'Duplicate-submit regression',
        ];

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/payments/'.$payment->id.'/refund', $payload)
            ->assertRedirect();

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/payments/'.$payment->id.'/refund', $payload)
            ->assertRedirect();

        $this->assertSame(1, Refund::query()
            ->where('idempotency_key', $idempotencyKey)
            ->count());
        $this->assertSame('partially_paid', $reservation->fresh()->payment_status);
    }

    public function test_payment_console_renders_idempotency_tokens(): void
    {
        $admin = $this->administrator();
        $this->reservation();

        $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/payments')
            ->assertOk()
            ->assertSee('name="idempotency_key"', false);
    }
    public function test_pending_online_refund_can_be_reconciled_from_payment_console(): void
    {
        config()->set('services.razorpay.key_id', 'rzp_test_key');
        config()->set('services.razorpay.key_secret', 'test-secret');
        config()->set('services.razorpay.api_base', 'https://api.razorpay.com/v1');

        $admin = $this->administrator();
        $reservation = $this->reservation();

        $payment = app(PaymentService::class)->record([
            'idempotency_key' => '31313131-3131-4313-8313-313131313131',
            'reservation_id' => $reservation->id,
            'method' => 'online_gateway',
            'amount' => 1000,
            'external_reference' => 'pay_reconcile_endpoint',
        ]);

        $refund = Refund::query()->create([
            'idempotency_key' => '32323232-3232-4323-8323-323232323232',
            'payment_id' => $payment->id,
            'amount' => 250,
            'status' => 'pending',
            'reason' => 'Provider response recovery',
        ]);

        Http::fake([
            'https://api.razorpay.com/v1/payments/pay_reconcile_endpoint/refunds' => Http::response([
                'items' => [[
                    'id' => 'rfnd_reconcile_endpoint',
                    'payment_id' => 'pay_reconcile_endpoint',
                    'amount' => 25000,
                    'currency' => 'INR',
                    'receipt' => $refund->idempotency_key,
                    'status' => 'processed',
                ]],
            ], 200),
        ]);

        $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/payments')
            ->assertOk()
            ->assertSee('Pending refund ₹250.00')
            ->assertSee('max="750"', false);

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/payments/refunds/'.$refund->id.'/reconcile')
            ->assertRedirect();

        $this->assertSame('succeeded', $refund->fresh()->status);
        $this->assertSame('rfnd_reconcile_endpoint', $refund->fresh()->external_reference);
        $this->assertSame('partially_paid', $reservation->fresh()->payment_status);

        $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/payments')
            ->assertOk();
    }

    public function test_targeted_front_desk_payment_form_renders_required_idempotency_token(): void
    {
        $admin = $this->administrator();
        $reservation = $this->reservation();

        $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/front-desk')
            ->assertOk()
            ->assertDontSee('name="idempotency_key"', false)
            ->assertDontSee('Target ID');

        $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/payments?reservation_id='.$reservation->id)
            ->assertOk()
            ->assertSee('The booking target is already set.')
            ->assertSee('name="idempotency_key"', false)
            ->assertSee($reservation->booking_number);
    }

    public function test_restaurant_quick_pay_uses_only_remaining_balance_after_partial_payment(): void
    {
        $admin = $this->administrator();

        $order = RestaurantOrder::query()->create([
            'order_number' => 'RO-QUICK-PAY-BALANCE',
            'order_type' => 'takeaway',
            'status' => 'served',
            'payment_status' => 'unpaid',
            'subtotal' => 500,
            'tax' => 0,
            'total' => 500,
        ]);

        app(PaymentService::class)->record([
            'idempotency_key' => '67676767-6767-4767-8767-676767676767',
            'restaurant_order_id' => $order->id,
            'method' => 'cash',
            'amount' => 200,
        ]);

        $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/restaurant')
            ->assertOk()
            ->assertSee('Due ₹300.00')
            ->assertSee('name="amount" value="300.00"', false)
            ->assertSee('name="external_reference"', false)
            ->assertSee('Pay balance');
    }

}
