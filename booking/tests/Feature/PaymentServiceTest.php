<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
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
            'external_reference'=>'upi-pay-1',
        ];

        $first = $service->record($payload);
        $second = $service->record($payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame('paid', $reservation->fresh()->payment_status);
    }

    public function test_non_cash_payment_requires_a_reference_at_service_boundary(): void
    {
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-PAY-REF-REQ',
            'public_token'=>'69696969-6969-4969-8969-696969696969',
            'check_in_date'=>today(),
            'check_out_date'=>today()->addDay(),
            'status'=>'confirmed',
            'pricing_status'=>'priced',
            'payment_status'=>'unpaid',
            'subtotal'=>1000,
            'tax'=>0,
            'total'=>1000,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payment reference is required');

        app(PaymentService::class)->record([
            'idempotency_key'=>'70707070-7070-4070-8070-707070707070',
            'reservation_id'=>$reservation->id,
            'method'=>'upi',
            'amount'=>500,
        ]);
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
            'external_reference'=>'card-pay-1',
        ]);

        $refund = $service->refund($payment, [
            'idempotency_key'=>'79797979-7979-4797-8797-797979797979',
            'amount'=>250,
            'refund_type'=>'other',
            'reason'=>'Guest adjustment',
        ]);

        $this->assertSame('pending_manual', $refund->status);
        $this->assertSame('paid', $reservation->fresh()->payment_status);

        $service->confirmManualRefund($refund, 'card-refund-1');

        $this->assertSame('partially_paid', $reservation->fresh()->payment_status);
        $this->assertDatabaseHas('refunds', [
            'payment_id'=>$payment->id,
            'amount'=>250,
            'refund_type'=>'other',
            'status'=>'succeeded',
        ]);
    }
    public function test_revenue_adjustment_refund_requires_an_issued_invoice(): void
    {
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-PAY-ADJUSTMENT-GUARD',
            'public_token'=>'71717171-7171-4171-8171-717171717171',
            'check_in_date'=>today(),
            'check_out_date'=>today()->addDay(),
            'status'=>'confirmed',
            'pricing_status'=>'priced',
            'payment_status'=>'unpaid',
            'subtotal'=>1000,
            'tax'=>0,
            'total'=>1000,
        ]);

        $service = app(PaymentService::class);
        $payment = $service->record([
            'idempotency_key'=>'72727272-7272-4272-8272-727272727272',
            'reservation_id'=>$reservation->id,
            'method'=>'cash',
            'amount'=>1000,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('require an issued invoice');

        $service->refund($payment, [
            'idempotency_key'=>'73737373-7373-4373-8373-737373737373',
            'amount'=>100,
            'refund_type'=>'service_recovery',
            'reason'=>'Service recovery before invoice',
        ]);
    }

    public function test_restaurant_payment_requires_a_served_non_room_service_order(): void
    {
        $order = RestaurantOrder::query()->create([
            'order_number'=>'RO-PAY-GUARD',
            'order_type'=>'dine_in',
            'status'=>'accepted',
            'payment_status'=>'unpaid',
            'subtotal'=>500,
            'tax'=>0,
            'total'=>500,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('served restaurant order');

        app(PaymentService::class)->record([
            'idempotency_key'=>'91919191-9191-4191-8191-919191919191',
            'restaurant_order_id'=>$order->id,
            'method'=>'cash',
            'amount'=>500,
        ]);
    }

    public function test_payment_idempotency_key_cannot_be_reused_for_a_different_request(): void
    {
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-PAY-IDEMPOTENCY-CONFLICT',
            'public_token'=>'15151515-1515-4151-8151-151515151515',
            'check_in_date'=>today(),
            'check_out_date'=>today()->addDay(),
            'status'=>'confirmed',
            'pricing_status'=>'priced',
            'payment_status'=>'unpaid',
            'subtotal'=>1000,
            'tax'=>0,
            'total'=>1000,
        ]);

        $service = app(PaymentService::class);
        $key = '16161616-1616-4161-8161-161616161616';

        $service->record([
            'idempotency_key'=>$key,
            'reservation_id'=>$reservation->id,
            'method'=>'cash',
            'amount'=>100,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('different payment request');

        $service->record([
            'idempotency_key'=>$key,
            'reservation_id'=>$reservation->id,
            'method'=>'cash',
            'amount'=>200,
        ]);
    }

    public function test_refund_idempotency_key_cannot_be_reused_for_a_different_request(): void
    {
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-REFUND-IDEMPOTENCY-CONFLICT',
            'public_token'=>'17171717-1717-4171-8171-171717171717',
            'check_in_date'=>today(),
            'check_out_date'=>today()->addDay(),
            'status'=>'confirmed',
            'pricing_status'=>'priced',
            'payment_status'=>'unpaid',
            'subtotal'=>1000,
            'tax'=>0,
            'total'=>1000,
        ]);

        $service = app(PaymentService::class);
        $payment = $service->record([
            'idempotency_key'=>'18181818-1818-4181-8181-181818181818',
            'reservation_id'=>$reservation->id,
            'method'=>'cash',
            'amount'=>500,
        ]);

        $key = '19191919-1919-4191-8191-191919191919';
        $service->refund($payment, [
            'idempotency_key'=>$key,
            'amount'=>100,
            'reason'=>'First refund',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('different refund request');

        $service->refund($payment, [
            'idempotency_key'=>$key,
            'amount'=>150,
            'reason'=>'Changed refund',
        ]);
    }

}
