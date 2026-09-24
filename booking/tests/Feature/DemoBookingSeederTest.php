<?php

namespace Tests\Feature;

use Database\Seeders\DemoBookingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoBookingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_booking_seeder_is_idempotent_and_populates_booking_prerequisites(): void
    {
        $this->seed(DemoBookingSeeder::class);
        $this->seed(DemoBookingSeeder::class);

        $this->assertDatabaseHas('rate_plans', [
            'code' => 'demo-room-only',
            'name' => 'Room Only (Demo)',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('rate_plans', [
            'code' => 'demo-room-breakfast',
            'name' => 'Room + Breakfast (Demo)',
            'includes_breakfast' => true,
            'is_active' => true,
        ]);

        foreach (['DEMO-C101', 'DEMO-C102', 'DEMO-CL201', 'DEMO-CL202', 'DEMO-P301', 'DEMO-P302'] as $number) {
            $this->assertDatabaseHas('rooms', [
                'number' => $number,
                'status' => 'active',
            ]);
        }

        $this->assertSame(
            6,
            \App\Models\Room::query()->where('number', 'like', 'DEMO-%')->count()
        );

        $this->assertSame(
            6,
            \App\Models\RoomRate::query()
                ->whereDate('starts_on', '2026-01-01')
                ->whereDate('ends_on', '2030-12-31')
                ->count()
        );
    }
}
