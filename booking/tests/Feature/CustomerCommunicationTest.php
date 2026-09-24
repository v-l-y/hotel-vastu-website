<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Services\CustomerMessageService;
use App\Services\PreArrivalReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_page_shows_clear_booking_success_message(): void
    {
        $reservation = Reservation::query()->create([
            'booking_number' => 'HV-SUCCESS-1',
            'public_token' => '11111111-1111-4111-8111-111111111111',
            'check_in_date' => today()->addDay(),
            'check_out_date' => today()->addDays(2),
            'adults' => 2,
            'children' => 0,
            'status' => 'confirmed',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => 3000,
            'tax' => 0,
            'total' => 3000,
        ]);

        $this->get('/confirmation/'.$reservation->public_token)
            ->assertOk()
            ->assertSee('Booking confirmed successfully!')
            ->assertSee('Your room has been booked at Hotel Vastu Premium.');
    }

    public function test_no_show_customer_notification_sends_email_copy(): void
    {
        Mail::fake();

        $reservation = $this->reservationWithGuest('HV-NOSHOW-MSG', today(), 'noshow@example.com');
        $reservation->update(['status' => 'no_show']);

        app(CustomerMessageService::class)->sendNoShow($reservation->fresh());

        Mail::assertSentCount(1);
    }

    public function test_pre_arrival_reminder_is_sent_only_once(): void
    {
        Mail::fake();

        $reservation = $this->reservationWithGuest(
            'HV-REMINDER-1',
            today()->addDay(),
            'reminder@example.com'
        );

        $service = app(PreArrivalReminderService::class);

        $this->assertSame(1, $service->sendForTomorrow());
        $this->assertNotNull($reservation->fresh()->pre_arrival_reminder_sent_at);
        $this->assertSame(0, $service->sendForTomorrow());

        Mail::assertSentCount(1);
    }

    private function reservationWithGuest(string $bookingNumber, mixed $checkIn, string $email): Reservation
    {
        $reservation = Reservation::query()->create([
            'booking_number' => $bookingNumber,
            'public_token' => (string) \Illuminate\Support\Str::uuid(),
            'check_in_date' => $checkIn,
            'check_out_date' => \Carbon\CarbonImmutable::parse($checkIn)->addDay(),
            'adults' => 2,
            'children' => 0,
            'status' => 'confirmed',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => 3000,
            'tax' => 0,
            'total' => 3000,
        ]);

        $guest = Guest::query()->create([
            'first_name' => 'Guest',
            'last_name' => 'Test',
            'phone' => '+91 90000 00000',
            'email' => $email,
        ]);

        ReservationGuest::query()->create([
            'reservation_id' => $reservation->id,
            'guest_id' => $guest->id,
            'role' => 'primary',
        ]);

        return $reservation;
    }
}
