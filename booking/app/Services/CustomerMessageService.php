<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Reservation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class CustomerMessageService
{
    public function sendOtp(string $phone, string $code): void
    {
        $message = "Hotel Vastu verification code: {$code}. Valid for 10 minutes. Do not share this code.";
        $this->sendSms($phone, $message, true);
    }

    public function sendBookingConfirmation(Reservation $reservation): void
    {
        $reservation->loadMissing(['guestLinks.guest']);
        $guest = $reservation->guestLinks->first()?->guest;
        $statusUrl = route('booking.confirmation', ['token' => $reservation->public_token]);
        $paymentStatus = str_replace('_', ' ', ucfirst((string) $reservation->payment_status));

        $message = "Booking confirmed at Hotel Vastu Premium. {$reservation->booking_number}. "
            ."Check-in {$reservation->check_in_date->format('d M Y')}, "
            ."check-out {$reservation->check_out_date->format('d M Y')}. "
            ."Total INR {$reservation->total}. Payment: {$paymentStatus}. "
            ."Status: {$statusUrl}";

        $this->sendSmsBestEffort((string) ($guest?->phone ?? ''), $message);

        if ($guest?->email) {
            $body = "Your Hotel Vastu Premium booking is confirmed.\n\n"
                ."Booking number: {$reservation->booking_number}\n"
                ."Check-in: {$reservation->check_in_date->format('d M Y')}\n"
                ."Check-out: {$reservation->check_out_date->format('d M Y')}\n"
                ."Total: INR {$reservation->total}\n"
                ."Payment status: {$paymentStatus}\n\n"
                ."View booking status: {$statusUrl}\n\n"
                ."Please keep this secure link for your booking updates.\n";

            $this->sendEmailBestEffort(
                $guest->email,
                'Booking confirmed - '.$reservation->booking_number.' | Hotel Vastu Premium',
                $body
            );
        }
    }

    public function sendPreArrivalReminder(Reservation $reservation): void
    {
        $reservation->loadMissing(['guestLinks.guest']);
        $guest = $reservation->guestLinks->first()?->guest;
        $statusUrl = route('booking.confirmation', ['token' => $reservation->public_token]);

        $message = "Reminder: your Hotel Vastu Premium stay starts "
            .$reservation->check_in_date->format('d M Y').". "
            ."Booking {$reservation->booking_number}. "
            ."If your plans changed, please contact the hotel. {$statusUrl}";

        $this->sendSmsBestEffort((string) ($guest?->phone ?? ''), $message);

        if ($guest?->email) {
            $body = "Your stay at Hotel Vastu Premium is tomorrow.\n\n"
                ."Booking number: {$reservation->booking_number}\n"
                ."Check-in: {$reservation->check_in_date->format('d M Y')}\n"
                ."Check-out: {$reservation->check_out_date->format('d M Y')}\n"
                ."Booking status: {$statusUrl}\n\n"
                ."If your plans have changed, please contact the hotel.\n";

            $this->sendEmailBestEffort(
                $guest->email,
                'Stay reminder - '.$reservation->booking_number.' | Hotel Vastu Premium',
                $body
            );
        }
    }

    public function sendNoShow(Reservation $reservation): void
    {
        $reservation->loadMissing(['guestLinks.guest']);
        $guest = $reservation->guestLinks->first()?->guest;
        $statusUrl = route('booking.confirmation', ['token' => $reservation->public_token]);

        $message = "Hotel Vastu Premium booking {$reservation->booking_number} was marked no-show. "
            ."No refund is processed automatically. Booking status: {$statusUrl}";

        $this->sendSmsBestEffort((string) ($guest?->phone ?? ''), $message);

        if ($guest?->email) {
            $body = "Your Hotel Vastu Premium reservation was marked as no-show.\n\n"
                ."Booking number: {$reservation->booking_number}\n"
                ."Scheduled check-in: {$reservation->check_in_date->format('d M Y')}\n"
                ."Status: No show\n\n"
                ."No refund is processed automatically. Any refund or adjustment follows the hotel's applicable booking policy.\n"
                ."View booking status: {$statusUrl}\n";

            $this->sendEmailBestEffort(
                $guest->email,
                'No-show status - '.$reservation->booking_number.' | Hotel Vastu Premium',
                $body
            );
        }
    }

    public function sendCheckout(Reservation $reservation, Invoice $invoice): void
    {
        $reservation->loadMissing(['guestLinks.guest', 'feedback']);
        $guest = $reservation->guestLinks->first()?->guest;
        $invoiceUrl = route('booking.invoice', ['token' => $reservation->public_token]);
        $feedbackUrl = $reservation->feedback
            ? route('booking.feedback.show', ['token' => $reservation->feedback->token])
            : null;

        $message = "Thank you for staying at Hotel Vastu. Invoice: {$invoiceUrl}";
        if ($feedbackUrl) {
            $message .= " Feedback: {$feedbackUrl}";
        }

        $this->sendSmsBestEffort((string) ($guest?->phone ?? ''), $message);

        if ($guest?->email) {
            $body = "Thank you for staying at Hotel Vastu Premium.\n\n"
                ."Booking: {$reservation->booking_number}\n"
                ."Invoice: {$invoice->invoice_number}\n"
                ."Invoice link: {$invoiceUrl}\n";

            if ($feedbackUrl) {
                $body .= "Rate your stay: {$feedbackUrl}\n";
            }

            $this->sendEmailBestEffort($guest->email, 'Hotel Vastu checkout '.$reservation->booking_number, $body);
        }
    }

    private function sendSmsBestEffort(string $phone, string $message): void
    {
        if ($phone === '') {
            return;
        }

        try {
            $this->sendSms($phone, $message, false);
        } catch (Throwable $exception) {
            Log::warning('Customer SMS delivery failed.', [
                'phone' => $this->maskPhone($phone),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendSms(string $phone, string $message, bool $required): void
    {
        $driver = (string) config('services.sms.driver', 'log');

        if ($driver === 'log') {
            if ($required && app()->environment('production')) {
                throw new RuntimeException('SMS verification provider is not configured.');
            }

            Log::info('Customer SMS', ['phone' => $phone, 'message' => $message]);
            return;
        }

        if ($driver !== 'webhook') {
            throw new RuntimeException('Unsupported SMS driver.');
        }

        $url = (string) config('services.sms.webhook_url');
        if ($url === '') {
            throw new RuntimeException('SMS webhook URL is not configured.');
        }

        $request = Http::acceptJson()->timeout(10);
        $token = (string) config('services.sms.webhook_token');
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->post($url, [
            'to' => $phone,
            'message' => $message,
            'sender_id' => config('services.sms.sender_id'),
        ]);

        if ($required) {
            $response->throw();
        } elseif ($response->failed()) {
            throw new RuntimeException('SMS provider rejected the message.');
        }
    }

    private function sendEmailBestEffort(string $email, string $subject, string $body): void
    {
        try {
            Mail::raw($body, fn ($mail) => $mail->to($email)->subject($subject));
        } catch (Throwable $exception) {
            Log::warning('Customer email delivery failed.', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function maskPhone(string $phone): string
    {
        return strlen($phone) <= 4 ? '****' : str_repeat('*', max(0, strlen($phone) - 4)).substr($phone, -4);
    }
}
