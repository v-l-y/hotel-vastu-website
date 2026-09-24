<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationFeedback;
use App\Models\ReservationHold;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RatePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBookingVerificationFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_requires_mobile_otp_before_confirmation_and_feedback_is_secure(): void
    {
        $type = RoomType::query()->create([
            'code' => 'otp-room',
            'name' => 'OTP Room',
            'base_rate' => 1800,
            'is_active' => true,
        ]);
        Room::query()->create([
            'room_type_id' => $type->id,
            'number' => 'OTP101',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'otp-plan',
            'name' => 'OTP Plan',
            'is_active' => true,
        ]);

        $hold = ReservationHold::query()->create([
            'token' => '55555555-5555-4555-8555-555555555555',
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'check_in_date' => today()->addDays(2),
            'check_out_date' => today()->addDays(3),
            'quantity' => 1,
            'adults' => 2,
            'children' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->post('/holds/'.$hold->token.'/confirm', [
            'first_name' => 'Verified',
            'last_name' => 'Guest',
            'phone' => '+91 90000 00000',
            'email' => 'verified@example.com',
        ])->assertRedirect('/holds/'.$hold->token.'/verify');

        $this->assertSame(0, Reservation::query()->count());

        $this->post('/holds/'.$hold->token.'/verify', ['otp' => '123456'])
            ->assertRedirect();

        $reservation = Reservation::query()->firstOrFail();
        $feedback = ReservationFeedback::query()->where('reservation_id', $reservation->id)->firstOrFail();

        $this->get('/feedback/'.$feedback->token)
            ->assertOk()
            ->assertSee('Feedback becomes available after checkout');

        $reservation->update(['status' => 'checked_out']);

        $this->post('/feedback/'.$feedback->token, [
            'overall_rating' => 5,
            'cleanliness_rating' => 5,
            'service_rating' => 4,
            'food_rating' => 4,
            'comment' => 'Good stay.',
        ])->assertRedirect('/feedback/'.$feedback->token);

        $this->assertDatabaseHas('reservation_feedbacks', [
            'reservation_id' => $reservation->id,
            'overall_rating' => 5,
            'service_rating' => 4,
        ]);
    }
}
