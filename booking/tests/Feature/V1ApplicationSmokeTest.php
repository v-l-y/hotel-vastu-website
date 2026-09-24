<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationHold;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class V1ApplicationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_booking_flow_runs_from_search_through_confirmation_page(): void
    {
        $roomType = RoomType::query()->create([
            'code' => 'v1-smoke-room',
            'name' => 'V1 Smoke Room',
            'max_adults' => 2,
            'max_children' => 1,
            'base_rate' => 1500,
            'is_active' => true,
        ]);

        Room::query()->create([
            'room_type_id' => $roomType->id,
            'number' => 'V101',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);

        $ratePlan = RatePlan::query()->create([
            'code' => 'v1-smoke-plan',
            'name' => 'V1 Smoke Plan',
            'is_active' => true,
        ]);

        $checkIn = today()->addDays(2)->toDateString();
        $checkOut = today()->addDays(3)->toDateString();

        $this->get('/availability?'.http_build_query([
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'room_type_id' => $roomType->id,
            'rate_plan_id' => $ratePlan->id,
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
        ]))
            ->assertOk()
            ->assertSee('Continue to guest details');

        $holdResponse = $this->post('/holds', [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'room_type_id' => $roomType->id,
            'rate_plan_id' => $ratePlan->id,
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
        ]);

        $hold = ReservationHold::query()->firstOrFail();
        $holdResponse->assertRedirect('/holds/'.$hold->token.'/guest');

        $this->get('/holds/'.$hold->token.'/guest')
            ->assertOk()
            ->assertSee('Guest details')
            ->assertSee('V1 Smoke Room');

        $otpResponse = $this->post('/holds/'.$hold->token.'/confirm', [
            'first_name' => 'V1',
            'last_name' => 'Guest',
            'phone' => '+91 90000 00000',
            'email' => 'v1-smoke@example.com',
            'special_request' => 'Smoke flow',
        ]);

        $otpResponse->assertRedirect('/holds/'.$hold->token.'/verify');
        $this->assertSame(0, Reservation::query()->count());

        $this->get('/holds/'.$hold->token.'/verify')
            ->assertOk()
            ->assertSee('Verify your mobile');

        $confirmResponse = $this->post('/holds/'.$hold->token.'/verify', [
            'otp' => '123456',
        ]);

        $reservation = Reservation::query()->firstOrFail();

        $confirmResponse->assertRedirect('/confirmation/'.$reservation->public_token);
        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame('priced', $reservation->pricing_status);
        $this->assertSame('1500.00', $reservation->total);

        $this->get('/confirmation/'.$reservation->public_token)
            ->assertOk()
            ->assertSee($reservation->booking_number)
            ->assertSee('Booking confirmation')
            ->assertSee('V1 Smoke Room')
            ->assertSee('V1 Smoke Plan')
            ->assertDontSee('A confirmation has been sent');
    }

    public function test_administrator_can_render_every_core_v1_admin_page(): void
    {
        $admin = AdminUser::query()->create([
            'name' => 'V1 Administrator',
            'email' => 'v1-admin@example.com',
            'password_hash' => password_hash('test-password-123', PASSWORD_DEFAULT),
            'role' => 'administrator',
            'is_active' => true,
        ]);

        foreach ([
            '/admin',
            '/admin/front-desk',
            '/admin/restaurant',
            '/admin/payments',
            '/admin/reports',
            '/admin/setup',
            '/admin/users',
        ] as $uri) {
            $this->withSession(['admin_user_id' => $admin->id])
                ->get($uri)
                ->assertOk();
        }
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'hotel-vastu-booking',
            ]);
    }
}
