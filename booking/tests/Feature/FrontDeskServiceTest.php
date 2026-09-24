<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\FrontDeskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontDeskServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkin_creates_stay_room_and_folio_charge(): void
    {
        $type = RoomType::query()->create(['code'=>'classic','name'=>'Classic Room','base_rate'=>2000,'is_active'=>true]);
        $plan = RatePlan::query()->create(['code'=>'standard','name'=>'Standard','is_active'=>true]);
        $room = Room::query()->create(['room_type_id'=>$type->id,'number'=>'101','status'=>'active']);
        $guest = Guest::query()->create(['first_name'=>'Guest','phone'=>'9000000000']);
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-CHECKIN-1','public_token'=>'44444444-4444-4444-8444-444444444444',
            'check_in_date'=>today(),'check_out_date'=>today()->addDay(),
            'status'=>'confirmed','pricing_status'=>'priced','payment_status'=>'unpaid',
            'subtotal'=>2000,'tax'=>0,'total'=>2000,
        ]);
        ReservationRoom::query()->create([
            'reservation_id'=>$reservation->id,'room_type_id'=>$type->id,
            'rate_plan_id'=>$plan->id,'quantity'=>1,'nightly_rate'=>2000,
        ]);
        ReservationGuest::query()->create(['reservation_id'=>$reservation->id,'guest_id'=>$guest->id,'role'=>'primary']);

        $stay = app(FrontDeskService::class)->checkIn($reservation, [$room->id]);

        $this->assertSame('checked_in', $stay->status);
        $this->assertDatabaseHas('folios', ['stay_id'=>$stay->id,'reservation_id'=>$reservation->id]);
        $this->assertDatabaseHas('folio_charges', ['category'=>'room','amount'=>2000]);
        $this->assertDatabaseHas('stay_rooms', ['stay_id'=>$stay->id,'room_id'=>$room->id]);
    }
}
