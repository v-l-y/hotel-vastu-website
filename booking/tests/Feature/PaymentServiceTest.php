<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame(1, \App\Models\Payment::query()->count());
        $this->assertSame('paid', $reservation->fresh()->payment_status);
    }
}
