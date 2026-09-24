<?php

namespace Tests\Feature;

use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantTable;
use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoRestaurantSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_restaurant_seeder_is_idempotent_and_populates_restaurant_prerequisites(): void
    {
        $this->seed(DemoRestaurantSeeder::class);
        $this->seed(DemoRestaurantSeeder::class);

        $this->assertSame(6, RestaurantCategory::query()->count());
        $this->assertSame(20, RestaurantMenuItem::query()->count());
        $this->assertSame(8, RestaurantTable::query()->count());

        $this->assertDatabaseHas('restaurant_categories', [
            'name' => 'Main Course',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $mainCourse = RestaurantCategory::query()->where('name', 'Main Course')->firstOrFail();

        $this->assertDatabaseHas('restaurant_menu_items', [
            'restaurant_category_id' => $mainCourse->id,
            'name' => 'Paneer Butter Masala',
            'price' => 320,
            'is_vegetarian' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('restaurant_menu_items', [
            'name' => 'Chicken Biryani',
            'price' => 360,
            'is_vegetarian' => false,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('restaurant_tables', [
            'code' => 'T01',
            'name' => 'Table 1',
            'capacity' => 2,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('restaurant_tables', [
            'code' => 'T08',
            'name' => 'Table 8',
            'capacity' => 8,
            'status' => 'available',
            'is_active' => true,
        ]);
    }
}
