<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmReservationRequest;
use App\Models\BookingVerification;
use App\Models\ReservationHold;
use App\Services\CustomerMessageService;
use App\Services\PricingService;
use App\Services\PromotionService;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use RuntimeException;

class BookingVerificationController extends Controller
{
    public function send(
        string $token,
        ConfirmReservationRequest $request,
        CustomerMessageService $messages,
        PricingService $pricing,
        PromotionService $promotions
    ): RedirectResponse {
        $hold = ReservationHold::query()
            ->active()
            ->where('token', $token)
            ->firstOrFail();

        $data = $request->validated();
        $promoCode = strtoupper(trim((string) ($data['promo_code'] ?? '')));

        if ($promoCode !== '') {
            try {
                $quote = $pricing->quote(
                    $hold->room_type_id,
                    $hold->rate_plan_id,
                    \Carbon\CarbonImmutable::parse($hold->check_in_date),
                    \Carbon\CarbonImmutable::parse($hold->check_out_date),
                    $hold->quantity
                );
                $promotions->preview($promoCode, (float) $quote['subtotal']);
            } catch (RuntimeException $exception) {
                return back()->withInput()->withErrors(['promo_code' => $exception->getMessage()]);
            }
        }

        $code = app()->environment('testing')
            ? '123456'
            : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        BookingVerification::query()->updateOrCreate(
            ['reservation_hold_id' => $hold->id],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'special_request' => $data['special_request'] ?? null,
                'promo_code' => $promoCode !== '' ? $promoCode : null,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'verified_at' => null,
            ]
        );

        try {
            $messages->sendOtp($data['phone'], $code);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['phone' => $exception->getMessage()]);
        }

        return redirect()->route('booking.otp.form', ['token' => $token]);
    }

    public function show(string $token): View
    {
        $hold = ReservationHold::query()
            ->active()
            ->where('token', $token)
            ->firstOrFail();

        $verification = BookingVerification::query()
            ->where('reservation_hold_id', $hold->id)
            ->firstOrFail();

        return view('booking.verify', compact('hold', 'verification'));
    }

    public function verify(
        string $token,
        Request $request,
        ReservationService $reservations,
        CustomerMessageService $messages
    ): RedirectResponse {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $hold = ReservationHold::query()
            ->active()
            ->where('token', $token)
            ->firstOrFail();

        $verification = BookingVerification::query()
            ->where('reservation_hold_id', $hold->id)
            ->firstOrFail();

        if ($verification->expires_at->isPast()) {
            return back()->withErrors(['otp' => 'Verification code expired. Please return to guest details and request a new code.']);
        }

        if ($verification->attempts >= 5) {
            return back()->withErrors(['otp' => 'Too many incorrect attempts. Please request a new verification code.']);
        }

        if (! Hash::check($data['otp'], $verification->code_hash)) {
            $verification->increment('attempts');
            return back()->withErrors(['otp' => 'Incorrect verification code.']);
        }

        $verification->update(['verified_at' => now()]);

        try {
            $reservation = $reservations->confirmHold($token, [
                'first_name' => $verification->first_name,
                'last_name' => $verification->last_name,
                'phone' => $verification->phone,
                'email' => $verification->email,
                'special_request' => $verification->special_request,
                'promo_code' => $verification->promo_code,
            ]);
        } catch (RuntimeException $exception) {
            return redirect()->route('booking.search')->withErrors(['booking' => $exception->getMessage()]);
        }

        $messages->sendBookingConfirmation($reservation);

        return redirect()->route('booking.confirmation', [
            'token' => $reservation->public_token,
        ]);
    }
}
