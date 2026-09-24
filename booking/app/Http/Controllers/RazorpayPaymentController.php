<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\OnlinePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use JsonException;
use RuntimeException;

class RazorpayPaymentController extends Controller
{
    public function start(string $token, OnlinePaymentService $service): View|RedirectResponse
    {
        $reservation = Reservation::query()
            ->where('public_token', $token)
            ->with('guestLinks.guest')
            ->firstOrFail();

        try {
            $order = $service->createOrder($reservation);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('booking.confirmation', ['token' => $token])
                ->withErrors(['payment' => $exception->getMessage()]);
        }

        return view('booking.payment', [
            'reservation' => $reservation,
            'order' => $order,
            'keyId' => $service->keyId(),
        ]);
    }

    public function verify(
        string $token,
        Request $request,
        OnlinePaymentService $service
    ): RedirectResponse {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:100'],
            'razorpay_payment_id' => ['required', 'string', 'max:100'],
            'razorpay_signature' => ['required', 'string', 'max:200'],
        ]);

        $reservation = Reservation::query()->where('public_token', $token)->firstOrFail();

        try {
            $service->verifyCheckout(
                $reservation,
                $data['razorpay_order_id'],
                $data['razorpay_payment_id'],
                $data['razorpay_signature']
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('booking.confirmation', ['token' => $token])
                ->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()
            ->route('booking.confirmation', ['token' => $token])
            ->with('status', 'Online payment verified successfully.');
    }

    public function webhook(Request $request, OnlinePaymentService $service): Response
    {
        try {
            $service->processWebhook(
                $request->getContent(),
                (string) $request->header('X-Razorpay-Signature', '')
            );
        } catch (JsonException|RuntimeException $exception) {
            return response('invalid webhook', 400);
        }

        return response('ok', 200);
    }
}
