<?php

namespace Tests\Feature;

use App\Mail\CustomerMessageMail;
use App\Models\AdminUser;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FrontDeskBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_front_desk_can_create_a_confirmed_priced_booking_and_send_confirmation(): void
    {
        Mail::fake();

        [$admin, $type, $plan] = $this->fixture();

        $response = $this->withSession($this->sessionFor($admin))
            ->from('/admin/front-desk')
            ->post('/admin/front-desk/reservations', [
                'first_name' => 'Walkin',
                'last_name' => 'Guest',
                'phone' => '+91 90000 00001',
                'email' => 'walkin@example.com',
                'check_in' => today()->toDateString(),
                'check_out' => today()->addDay()->toDateString(),
                'room_type_id' => $type->id,
                'rate_plan_id' => $plan->id,
                'rooms' => 1,
                'adults' => 2,
                'children' => 0,
                'special_request' => 'Late dinner',
            ]);

        $response->assertRedirect('/admin/front-desk');
        $response->assertSessionHas('status');

        $reservation = Reservation::query()->firstOrFail();

        $this->assertSame('front_desk', $reservation->source);
        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame('priced', $reservation->pricing_status);
        $this->assertSame('2500.00', $reservation->total);
        $this->assertDatabaseHas('guests', [
            'first_name' => 'Walkin',
            'email' => 'walkin@example.com',
        ]);
        $this->assertDatabaseHas('reservation_feedbacks', [
            'reservation_id' => $reservation->id,
        ]);
        $this->assertDatabaseHas('reservation_night_rates', [
            'reservation_id' => $reservation->id,
        ]);

        Mail::assertSent(CustomerMessageMail::class, function (CustomerMessageMail $mail) {
            return str_contains($mail->messageSubject, 'Booking confirmed - ')
                && str_contains($mail->messageBody, 'Your Hotel Vastu Premium booking is confirmed.');
        });
    }

    public function test_front_desk_booking_respects_live_inventory_and_does_not_oversell(): void
    {
        Mail::fake();

        [$admin, $type, $plan] = $this->fixture();

        $payload = [
            'first_name' => 'First',
            'last_name' => 'Guest',
            'phone' => '+91 90000 00002',
            'email' => 'first@example.com',
            'check_in' => today()->addDays(2)->toDateString(),
            'check_out' => today()->addDays(3)->toDateString(),
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'rooms' => 1,
            'adults' => 1,
            'children' => 0,
        ];

        $this->withSession($this->sessionFor($admin))
            ->from('/admin/front-desk')
            ->post('/admin/front-desk/reservations', $payload)
            ->assertRedirect('/admin/front-desk');

        $second = $payload;
        $second['first_name'] = 'Second';
        $second['phone'] = '+91 90000 00003';
        $second['email'] = 'second@example.com';

        $this->withSession($this->sessionFor($admin))
            ->from('/admin/front-desk')
            ->post('/admin/front-desk/reservations', $second)
            ->assertRedirect('/admin/front-desk')
            ->assertSessionHasErrors('front_desk_booking');

        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_front_desk_screen_exposes_walk_in_booking_form_only_to_authorized_roles(): void
    {
        [$frontDesk] = $this->fixture();

        $this->withSession($this->sessionFor($frontDesk))
            ->get('/admin/front-desk')
            ->assertOk()
            ->assertSee('New / walk-in booking')
            ->assertSee('Check availability & confirm booking');

        $accounts = AdminUser::query()->create([
            'name' => 'Accounts',
            'email' => 'accounts-walkin@example.com',
            'password_hash' => password_hash('test-password-123', PASSWORD_DEFAULT),
            'role' => 'accounts',
            'is_active' => true,
        ]);

        $this->withSession($this->sessionFor($accounts))
            ->post('/admin/front-desk/reservations', [
                'first_name' => 'Blocked',
            ])
            ->assertForbidden();
    }

    private function fixture(): array
    {
        $admin = AdminUser::query()->create([
            'name' => 'Front Desk',
            'email' => 'frontdesk-booking@example.com',
            'password_hash' => password_hash('test-password-123', PASSWORD_DEFAULT),
            'role' => 'front_desk',
            'is_active' => true,
        ]);

        $type = RoomType::query()->create([
            'code' => 'walkin-classic',
            'name' => 'Classic Room',
            'max_adults' => 2,
            'max_children' => 2,
            'base_rate' => 2500,
            'is_active' => true,
        ]);

        $plan = RatePlan::query()->create([
            'code' => 'walkin-standard',
            'name' => 'Room Only',
            'is_active' => true,
        ]);

        Room::query()->create([
            'room_type_id' => $type->id,
            'number' => 'W101',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);

        return [$admin, $type, $plan];
    }

    private function sessionFor(AdminUser $admin): array
    {
        return [
            'admin_user_id' => $admin->id,
            'admin_session_version' => $admin->session_version,
        ];
    }
}
