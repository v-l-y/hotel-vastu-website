<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Payment;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\FrontDeskService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontDeskServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createReservationFixture(string $suffix = '1'): array
    {
        $type = RoomType::query()->create([
            'code'=>'classic-'.$suffix,
            'name'=>'Classic Room',
            'base_rate'=>2000,
            'is_active'=>true,
        ]);
        $plan = RatePlan::query()->create([
            'code'=>'standard-'.$suffix,
            'name'=>'Standard',
            'is_active'=>true,
        ]);
        $room = Room::query()->create([
            'room_type_id'=>$type->id,
            'number'=>'10'.$suffix,
            'status'=>'active',
        ]);
        $guest = Guest::query()->create([
            'first_name'=>'Guest',
            'phone'=>'900000000'.$suffix,
        ]);
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-CHECKIN-'.$suffix,
            'public_token'=>sprintf('44444444-4444-4444-8444-%012d', (int) $suffix),
            'check_in_date'=>today(),
            'check_out_date'=>today()->addDay(),
            'status'=>'confirmed',
            'pricing_status'=>'priced',
            'payment_status'=>'unpaid',
            'subtotal'=>2000,
            'tax'=>0,
            'total'=>2000,
        ]);
        ReservationRoom::query()->create([
            'reservation_id'=>$reservation->id,
            'room_type_id'=>$type->id,
            'rate_plan_id'=>$plan->id,
            'quantity'=>1,
            'nightly_rate'=>2000,
        ]);
        ReservationGuest::query()->create([
            'reservation_id'=>$reservation->id,
            'guest_id'=>$guest->id,
            'role'=>'primary',
        ]);

        return [$reservation, $room];
    }

    public function test_checkin_creates_stay_room_and_folio_charge(): void
    {
        [$reservation, $room] = $this->createReservationFixture('1');

        $stay = app(FrontDeskService::class)->checkIn($reservation, [$room->id]);

        $this->assertSame('checked_in', $stay->status);
        $this->assertDatabaseHas('folios', ['stay_id'=>$stay->id,'reservation_id'=>$reservation->id]);
        $this->assertDatabaseHas('folio_charges', ['category'=>'room','amount'=>2000]);
        $this->assertDatabaseHas('stay_rooms', ['stay_id'=>$stay->id,'room_id'=>$room->id]);
    }

    public function test_advance_payment_moves_to_folio_and_settled_checkout_creates_invoice(): void
    {
        [$reservation, $room] = $this->createReservationFixture('2');

        $advance = app(PaymentService::class)->record([
            'idempotency_key'=>'10101010-1010-4010-8010-101010101010',
            'reservation_id'=>$reservation->id,
            'method'=>'upi',
            'amount'=>2000,
        ]);

        $stay = app(FrontDeskService::class)->checkIn($reservation->fresh(), [$room->id]);
        $movedPayment = Payment::query()->findOrFail($advance->id);

        $this->assertNull($movedPayment->reservation_id);
        $this->assertSame($stay->folio->id, $movedPayment->folio_id);
        $this->assertSame('0.00', $stay->folio->fresh()->balance);

        $result = app(FrontDeskService::class)->checkOut($stay);

        $this->assertSame('checked_out', $result['stay']->status);
        $this->assertSame('closed', $result['folio']->status);
        $this->assertSame('0.00', $result['invoice']->balance);
        $this->assertDatabaseHas('invoices', ['folio_id'=>$result['folio']->id]);
        $this->assertDatabaseHas('invoice_items', ['invoice_id'=>$result['invoice']->id, 'category'=>'room']);
    }

    public function test_occupied_room_housekeeping_status_is_locked_until_release(): void
    {
        [$reservation, $room] = $this->createReservationFixture('4');
        $room->update(['housekeeping_status'=>'inspected']);

        app(PaymentService::class)->record([
            'idempotency_key'=>'40404040-4040-4040-8040-404040404040',
            'reservation_id'=>$reservation->id,
            'method'=>'upi',
            'amount'=>2000,
        ]);

        $service = app(FrontDeskService::class);
        $stay = $service->checkIn($reservation->fresh(), [$room->id]);

        try {
            $service->updateHousekeeping($room->fresh(), 'out_of_order');
            $this->fail('Occupied room housekeeping update should have been rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Occupied rooms cannot be changed by housekeeping until checkout or room transfer.',
                $exception->getMessage()
            );
        }

        $this->assertSame('inspected', $room->fresh()->housekeeping_status);
        $this->assertDatabaseHas('stay_rooms', [
            'stay_id'=>$stay->id,
            'room_id'=>$room->id,
            'released_at'=>null,
        ]);

        $service->checkOut($stay);

        $this->assertSame('dirty', $room->fresh()->housekeeping_status);

        $service->updateHousekeeping($room->fresh(), 'out_of_order');

        $this->assertSame('out_of_order', $room->fresh()->housekeeping_status);
    }

    public function test_room_lifecycle_checkin_checkout_and_housekeeping_is_enforced(): void
    {
        [$reservation, $room] = $this->createReservationFixture('3');
        $room->update(['housekeeping_status'=>'inspected']);

        app(PaymentService::class)->record([
            'idempotency_key'=>'30303030-3030-4030-8030-303030303030',
            'reservation_id'=>$reservation->id,
            'method'=>'upi',
            'amount'=>2000,
        ]);

        $service = app(FrontDeskService::class);
        $stay = $service->checkIn($reservation->fresh(), [$room->id]);

        $this->assertSame('checked_in', $stay->status);
        $this->assertSame('checked_in', $reservation->fresh()->status);
        $this->assertSame('inspected', $room->fresh()->housekeeping_status);
        $this->assertDatabaseHas('stay_rooms', [
            'stay_id'=>$stay->id,
            'room_id'=>$room->id,
            'released_at'=>null,
        ]);

        $result = $service->checkOut($stay);

        $this->assertSame('checked_out', $result['stay']->status);
        $this->assertSame('checked_out', $reservation->fresh()->status);
        $this->assertSame('dirty', $room->fresh()->housekeeping_status);
        $this->assertDatabaseMissing('stay_rooms', [
            'stay_id'=>$stay->id,
            'room_id'=>$room->id,
            'released_at'=>null,
        ]);

        $service->updateHousekeeping($room->fresh(), 'clean');

        $this->assertSame('clean', $room->fresh()->housekeeping_status);
    }
}
