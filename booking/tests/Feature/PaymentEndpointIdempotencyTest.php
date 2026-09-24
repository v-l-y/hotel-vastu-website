<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
