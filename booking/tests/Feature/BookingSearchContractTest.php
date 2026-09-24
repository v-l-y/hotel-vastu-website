<?php

namespace Tests\Feature;

use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingSearchContractTest extends TestCase
{
    use RefreshDatabase;


    public function test_public_availability_hides_internal_inventory_counts_and_guides_valid_dates(): void
    {
        $type = RoomType::query()->create([
            'code' => 'public-availability',
            'name' => 'Public Availability Room',
            'max_adults' => 2,
            'max_children' => 1,
            'base_rate' => 1200,
            'is_active' => true,
        ]);
        foreach (['P101', 'P102'] as $number) {
            Room::query()->create([
                'room_type_id' => $type->id,
                'number' => $number,
                'status' => 'active',
                'housekeeping_status' => 'clean',
            ]);
        }
        $plan = RatePlan::query()->create([
            'code' => 'public-plan',
            'name' => 'Public Plan',
            'is_active' => true,
        ]);

        $response = $this->get('/availability?'.http_build_query([
            'check_in' => today()->addDay()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
        ]));

        $response->assertOk()
            ->assertSee('Available for your selected dates.')
            ->assertDontSee('Total inventory')
            ->assertDontSee('Temporarily held')
            ->assertDontSee('Reserved')
            ->assertSee('min="'.today()->toDateString().'"', false)
            ->assertSee('min="'.today()->addDay()->toDateString().'"', false);
    }

    public function test_availability_search_blocks_over_capacity_request_before_hold(): void
    {
        $type = RoomType::query()->create([
            'code' => 'capacity-search',
            'name' => 'Capacity Search Room',
            'max_adults' => 2,
            'max_children' => 1,
            'base_rate' => 1000,
            'is_active' => true,
        ]);
        Room::query()->create([
            'room_type_id' => $type->id,
            'number' => 'C101',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'capacity-plan',
            'name' => 'Capacity Plan',
            'is_active' => true,
        ]);

        $response = $this->get('/availability?'.http_build_query([
            'check_in' => today()->addDay()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'rooms' => 1,
            'adults' => 3,
            'children' => 0,
        ]));

        $response->assertOk()
            ->assertSee('cannot accommodate this many adults')
            ->assertDontSee('Continue to guest details');
    }
}
