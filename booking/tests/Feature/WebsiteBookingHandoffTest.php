<?php

namespace Tests\Feature;

use App\Models\RatePlan;
use App\Models\ReservationHold;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteBookingHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_static_website_handoff_goes_directly_to_guest_details_with_an_atomic_hold(): void
    {
        $club = RoomType::query()->create([
            'code' => 'club',
            'name' => 'Club Room',
            'max_adults' => 2,
            'max_children' => 1,
            'base_rate' => 3000,
            'is_active' => true,
        ]);
        Room::query()->create([
            'room_type_id' => $club->id,
            'number' => 'CL101',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        RatePlan::query()->create([
            'code' => 'room-only',
            'name' => 'Room Only',
            'is_active' => true,
        ]);

        $checkIn = today()->addDay()->toDateString();
        $checkOut = today()->addDays(2)->toDateString();

        $response = $this->get('/?'.http_build_query([
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 2,
            'children' => 1,
            'room_code' => 'club',
        ]));

        $hold = ReservationHold::query()->firstOrFail();

        $response->assertRedirect('/holds/'.$hold->token.'/guest');
        $this->assertSame($club->id, $hold->room_type_id);
        $this->assertSame(2, $hold->adults);
        $this->assertSame(1, $hold->children);
        $this->assertSame(1, $hold->quantity);
    }

    public function test_room_page_room_code_only_prefills_search_without_validation_errors_or_hold(): void
    {
        $club = RoomType::query()->create([
            'code' => 'club',
            'name' => 'Club Room',
            'max_adults' => 2,
            'max_children' => 1,
            'base_rate' => 3000,
            'is_active' => true,
        ]);
        RatePlan::query()->create([
            'code' => 'room-only',
            'name' => 'Room Only',
            'is_active' => true,
        ]);

        $this->get('/?room_code=club')
            ->assertOk()
            ->assertSee('Check room availability')
            ->assertSee('value="'.$club->id.'" selected', false)
            ->assertDontSee('The check in field is required')
            ->assertDontSee('The check out field is required');

        $this->assertSame(0, ReservationHold::query()->count());
    }

    public function test_guest_details_show_price_before_otp(): void
    {
        $club = RoomType::query()->create([
            'code' => 'club',
            'name' => 'Club Room',
            'base_rate' => 3000,
            'is_active' => true,
        ]);
        Room::query()->create([
            'room_type_id' => $club->id,
            'number' => 'CL102',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'room-only',
            'name' => 'Room Only',
            'is_active' => true,
        ]);

        $hold = ReservationHold::query()->create([
            'token' => '77777777-7777-4777-8777-777777777777',
            'room_type_id' => $club->id,
            'rate_plan_id' => $plan->id,
            'check_in_date' => today()->addDay(),
            'check_out_date' => today()->addDays(2),
            'quantity' => 1,
            'adults' => 2,
            'children' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->get('/holds/'.$hold->token.'/guest')
            ->assertOk()
            ->assertSee('Room charges')
            ->assertSee('Tax')
            ->assertSee('Stay total')
            ->assertSee('3,000.00')
            ->assertSee('Send verification code');
    }

    public function test_sold_out_selected_category_shows_available_alternatives_without_losing_stay_details(): void
    {
        $club = RoomType::query()->create([
            'code' => 'club',
            'name' => 'Club Room',
            'base_rate' => 3000,
            'is_active' => true,
        ]);
        $classic = RoomType::query()->create([
            'code' => 'classic',
            'name' => 'Classic Room',
            'base_rate' => 2200,
            'is_active' => true,
        ]);

        Room::query()->create([
            'room_type_id' => $club->id,
            'number' => 'CL103',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        Room::query()->create([
            'room_type_id' => $classic->id,
            'number' => 'C103',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);

        $plan = RatePlan::query()->create([
            'code' => 'room-only',
            'name' => 'Room Only',
            'is_active' => true,
        ]);

        $checkIn = today()->addDay();
        $checkOut = today()->addDays(2);

        ReservationHold::query()->create([
            'token' => '88888888-8888-4888-8888-888888888888',
            'room_type_id' => $club->id,
            'rate_plan_id' => $plan->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'quantity' => 1,
            'adults' => 2,
            'children' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->get('/?'.http_build_query([
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => 2,
            'room_code' => 'club',
        ]))
            ->assertOk()
            ->assertSee('Club Room is no longer available')
            ->assertSee('Your stay details are saved')
            ->assertSee($checkIn->format('d M Y'))
            ->assertSee($checkOut->format('d M Y'))
            ->assertSee('Classic Room')
            ->assertSee('Choose Classic Room');
    }
}
