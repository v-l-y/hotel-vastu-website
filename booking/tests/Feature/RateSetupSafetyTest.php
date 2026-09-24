<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\RatePlan;
use App\Models\RoomRate;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateSetupSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlapping_dated_rate_is_rejected_through_setup_endpoint(): void
    {
        $admin = AdminUser::query()->create([
            'name' => 'Setup Admin',
            'email' => 'setup-rate@example.com',
            'password_hash' => password_hash('test-password-123', PASSWORD_DEFAULT),
            'role' => 'administrator',
            'is_active' => true,
        ]);

        $roomType = RoomType::query()->create([
            'code' => 'rate-lock-room',
            'name' => 'Rate Lock Room',
            'is_active' => true,
        ]);

        $ratePlan = RatePlan::query()->create([
            'code' => 'rate-lock-plan',
            'name' => 'Rate Lock Plan',
            'is_active' => true,
        ]);

        $first = [
            'room_type_id' => $roomType->id,
            'rate_plan_id' => $ratePlan->id,
            'starts_on' => today()->addDay()->toDateString(),
            'ends_on' => today()->addDays(3)->toDateString(),
            'nightly_rate' => 2500,
            'min_stay' => 1,
        ];

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/setup/room-rates', $first)
            ->assertRedirect();

        $this->withSession(['admin_user_id' => $admin->id])
            ->from('/admin/setup')
            ->post('/admin/setup/room-rates', [
                ...$first,
                'starts_on' => today()->addDays(2)->toDateString(),
                'ends_on' => today()->addDays(4)->toDateString(),
                'nightly_rate' => 3000,
            ])
            ->assertRedirect('/admin/setup')
            ->assertSessionHasErrors('starts_on');

        $this->assertSame(1, RoomRate::query()
            ->where('room_type_id', $roomType->id)
            ->where('rate_plan_id', $ratePlan->id)
            ->count());
    }
}
