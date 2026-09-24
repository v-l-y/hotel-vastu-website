<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Services\ReservationLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_reservation_releases_it_from_active_status(): void
    {
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-CANCEL-1','public_token'=>'88888888-8888-4888-8888-888888888888',
            'check_in_date'=>today()->addDay(),'check_out_date'=>today()->addDays(2),
            'status'=>'confirmed','pricing_status'=>'priced','payment_status'=>'unpaid',
        ]);

        $result = app(ReservationLifecycleService::class)->cancel($reservation);

        $this->assertSame('cancelled', $result->status);
    }

    public function test_no_show_is_allowed_on_checkin_date(): void
    {
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-NOSHOW-1','public_token'=>'99999999-9999-4999-8999-999999999999',
            'check_in_date'=>today(),'check_out_date'=>today()->addDay(),
            'status'=>'confirmed','pricing_status'=>'priced','payment_status'=>'unpaid',
        ]);

        $result = app(ReservationLifecycleService::class)->markNoShow($reservation);

        $this->assertSame('no_show', $result->status);
    }
}
