<?php

namespace Database\Seeders;

use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoBookingSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoBookingSeeder must not run in production.');
        }

        $this->call(HotelSetupSeeder::class);

        $ratePlans = collect([
            [
                'code' => 'demo-room-only',
                'name' => 'Room Only (Demo)',
                'includes_breakfast' => false,
            ],
            [
                'code' => 'demo-room-breakfast',
                'name' => 'Room + Breakfast (Demo)',
                'includes_breakfast' => true,
            ],
        ])->mapWithKeys(function (array $plan) {
            $ratePlan = RatePlan::query()->updateOrCreate(
                ['code' => $plan['code']],
                [
                    'name' => $plan['name'],
                    'includes_breakfast' => $plan['includes_breakfast'],
                    'is_active' => true,
                ]
            );

            return [$plan['code'] => $ratePlan];
        });

        $roomTypes = RoomType::query()
            ->whereIn('code', ['classic', 'club', 'premium'])
            ->get()
            ->keyBy('code');

        $rooms = [
            ['number' => 'DEMO-C101', 'floor' => '1', 'room_type' => 'classic'],
            ['number' => 'DEMO-C102', 'floor' => '1', 'room_type' => 'classic'],
            ['number' => 'DEMO-CL201', 'floor' => '2', 'room_type' => 'club'],
            ['number' => 'DEMO-CL202', 'floor' => '2', 'room_type' => 'club'],
            ['number' => 'DEMO-P301', 'floor' => '3', 'room_type' => 'premium'],
            ['number' => 'DEMO-P302', 'floor' => '3', 'room_type' => 'premium'],
        ];

        foreach ($rooms as $room) {
            $roomType = $roomTypes->get($room['room_type']);

            if (! $roomType) {
                continue;
            }

            Room::query()->updateOrCreate(
                ['number' => $room['number']],
                [
                    'room_type_id' => $roomType->id,
                    'floor' => $room['floor'],
                    'status' => 'active',
                ]
            );
        }

        $nightlyRates = [
            'classic' => [
                'demo-room-only' => 2200,
                'demo-room-breakfast' => 2600,
            ],
            'club' => [
                'demo-room-only' => 3000,
                'demo-room-breakfast' => 3400,
            ],
            'premium' => [
                'demo-room-only' => 3800,
                'demo-room-breakfast' => 4200,
            ],
        ];

        foreach ($nightlyRates as $roomTypeCode => $plans) {
            $roomType = $roomTypes->get($roomTypeCode);

            if (! $roomType) {
                continue;
            }

            foreach ($plans as $ratePlanCode => $nightlyRate) {
                $ratePlan = $ratePlans->get($ratePlanCode);

                RoomRate::query()->updateOrCreate(
                    [
                        'room_type_id' => $roomType->id,
                        'rate_plan_id' => $ratePlan->id,
                    ],
                    [
                        'starts_on' => '2026-01-01',
                        'ends_on' => '2030-12-31',
                        'nightly_rate' => $nightlyRate,
                        'min_stay' => 1,
                        'max_stay' => 30,
                    ]
                );
            }
        }

        $this->command?->warn(
            'Demo booking data seeded for local/testing use only. Replace demo rooms and rates with actual hotel data before production.'
        );
    }
}
