<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_is_idempotent_and_updates_reservation_status(): void
    {
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-PAY-1','public_token'=>'66666666-6666-4666-8666-666666666666',
            'check_in_date'=>today(),'check_out_date'=>today()->addDay(),
            'status'=>'confirmed','pricing_status'=>'priced','payment_status'=>'unpaid',
            'subtotal'=>1000,'tax'=>0,'total'=>1000,
        ]);

        $service = app(PaymentService::class);
        $payload = [
            'idempotency_key'=>'77777777-7777-4777-8777-777777777777',
            'reservation_id'=>$reservation->id,
            'method'=>'upi',
            'amount'=>1000,
        ];

        $first = $service->record($payload);
        $second = $service->record($payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame('paid', $reservation->fresh()->payment_status);
    }

    public function test_manual_payment_cannot_exceed_outstanding_balance(): void
    {
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-PAY-3','public_token'=>'68686868-6868-4868-8868-686868686868',
            'check_in_date'=>today(),'check_out_date'=>today()->addDay(),
            'status'=>'confirmed','pricing_status'=>'priced','payment_status'=>'unpaid',
            'subtotal'=>1000,'tax'=>0,'total'=>1000,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds the outstanding balance');

        app(PaymentService::class)->record([
            'idempotency_key'=>'80808080-8080-4080-8080-808080808080',
            'reservation_id'=>$reservation->id,
            'method'=>'cash',
            'amount'=>1000.01,
        ]);
    }

    public function test_partial_refund_updates_reservation_payment_status(): void
    {
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-PAY-2','public_token'=>'67676767-6767-4767-8767-676767676767',
            'check_in_date'=>today(),'check_out_date'=>today()->addDay(),
            'status'=>'confirmed','pricing_status'=>'priced','payment_status'=>'unpaid',
            'subtotal'=>1000,'tax'=>0,'total'=>1000,
        ]);

        $service = app(PaymentService::class);
        $payment = $service->record([
            'idempotency_key'=>'78787878-7878-4787-8787-787878787878',
            'reservation_id'=>$reservation->id,
            'method'=>'card',
            'amount'=>1000,
        ]);

        $service->refund($payment, [
            'idempotency_key'=>'79797979-7979-4797-8797-797979797979',
            'amount'=>250,
            'reason'=>'Guest adjustment',
        ]);

        $this->assertSame('partially_paid', $reservation->fresh()->payment_status);
        $this->assertDatabaseHas('refunds', ['payment_id'=>$payment->id, 'amount'=>250]);
    }
}
