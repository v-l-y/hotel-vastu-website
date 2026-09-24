<?php

namespace Database\Seeders;

use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoRestaurantSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoRestaurantSeeder must not run in production.');
        }

        $categories = collect([
            ['name' => 'Starters', 'sort_order' => 10],
            ['name' => 'Main Course', 'sort_order' => 20],
            ['name' => 'Rice & Biryani', 'sort_order' => 30],
            ['name' => 'Breads', 'sort_order' => 40],
            ['name' => 'Beverages', 'sort_order' => 50],
            ['name' => 'Desserts', 'sort_order' => 60],
        ])->mapWithKeys(function (array $category) {
            $model = RestaurantCategory::query()->updateOrCreate(
                ['name' => $category['name']],
                [
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ]
            );

            return [$category['name'] => $model];
        });

        $menuItems = [
            ['category' => 'Starters', 'name' => 'Veg Pakora', 'price' => 160, 'is_vegetarian' => true],
            ['category' => 'Starters', 'name' => 'Paneer Tikka', 'price' => 280, 'is_vegetarian' => true],
            ['category' => 'Starters', 'name' => 'Chicken Tikka', 'price' => 340, 'is_vegetarian' => false],

            ['category' => 'Main Course', 'name' => 'Dal Tadka', 'price' => 220, 'is_vegetarian' => true],
            ['category' => 'Main Course', 'name' => 'Paneer Butter Masala', 'price' => 320, 'is_vegetarian' => true],
            ['category' => 'Main Course', 'name' => 'Mixed Veg Curry', 'price' => 260, 'is_vegetarian' => true],
            ['category' => 'Main Course', 'name' => 'Chicken Curry', 'price' => 360, 'is_vegetarian' => false],
            ['category' => 'Main Course', 'name' => 'Egg Curry', 'price' => 260, 'is_vegetarian' => false],

            ['category' => 'Rice & Biryani', 'name' => 'Steamed Rice', 'price' => 150, 'is_vegetarian' => true],
            ['category' => 'Rice & Biryani', 'name' => 'Jeera Rice', 'price' => 180, 'is_vegetarian' => true],
            ['category' => 'Rice & Biryani', 'name' => 'Veg Biryani', 'price' => 280, 'is_vegetarian' => true],
            ['category' => 'Rice & Biryani', 'name' => 'Chicken Biryani', 'price' => 360, 'is_vegetarian' => false],

            ['category' => 'Breads', 'name' => 'Tandoori Roti', 'price' => 35, 'is_vegetarian' => true],
            ['category' => 'Breads', 'name' => 'Plain Naan', 'price' => 55, 'is_vegetarian' => true],
            ['category' => 'Breads', 'name' => 'Butter Naan', 'price' => 65, 'is_vegetarian' => true],

            ['category' => 'Beverages', 'name' => 'Tea', 'price' => 40, 'is_vegetarian' => true],
            ['category' => 'Beverages', 'name' => 'Coffee', 'price' => 70, 'is_vegetarian' => true],
            ['category' => 'Beverages', 'name' => 'Cold Drink', 'price' => 60, 'is_vegetarian' => true],

            ['category' => 'Desserts', 'name' => 'Gulab Jamun', 'price' => 120, 'is_vegetarian' => true],
            ['category' => 'Desserts', 'name' => 'Ice Cream', 'price' => 120, 'is_vegetarian' => true],
        ];

        foreach ($menuItems as $menuItem) {
            $category = $categories->get($menuItem['category']);

            if (! $category) {
                continue;
            }

            RestaurantMenuItem::query()->updateOrCreate(
                [
                    'restaurant_category_id' => $category->id,
                    'name' => $menuItem['name'],
                ],
                [
                    'price' => $menuItem['price'],
                    'is_vegetarian' => $menuItem['is_vegetarian'],
                    'is_active' => true,
                ]
            );
        }

        foreach ([
            ['code' => 'T01', 'name' => 'Table 1', 'capacity' => 2],
            ['code' => 'T02', 'name' => 'Table 2', 'capacity' => 2],
            ['code' => 'T03', 'name' => 'Table 3', 'capacity' => 4],
            ['code' => 'T04', 'name' => 'Table 4', 'capacity' => 4],
            ['code' => 'T05', 'name' => 'Table 5', 'capacity' => 4],
            ['code' => 'T06', 'name' => 'Table 6', 'capacity' => 6],
            ['code' => 'T07', 'name' => 'Table 7', 'capacity' => 6],
            ['code' => 'T08', 'name' => 'Table 8', 'capacity' => 8],
        ] as $table) {
            RestaurantTable::query()->updateOrCreate(
                ['code' => $table['code']],
                [
                    'name' => $table['name'],
                    'capacity' => $table['capacity'],
                    'status' => 'available',
                    'is_active' => true,
                ]
            );
        }

        $this->command?->warn(
            'Demo restaurant menu and tables seeded for local/testing use only. Replace them with the hotel restaurant\'s actual menu, pricing, and table plan before production.'
        );
    }
}
