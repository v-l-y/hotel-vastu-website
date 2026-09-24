<?php

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;

class HotelSetupSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'classic', 'name' => 'Classic Room'],
            ['code' => 'club', 'name' => 'Club Room'],
            ['code' => 'premium', 'name' => 'Premium Room'],
        ] as $roomType) {
            RoomType::query()->updateOrCreate(
                ['code' => $roomType['code']],
                ['name' => $roomType['name'], 'is_active' => true]
            );
        }
    }
}
