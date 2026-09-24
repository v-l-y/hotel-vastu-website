<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmReservationRequest;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class ReservationController extends Controller
{
    public function store(
        string $token,
        ConfirmReservationRequest $request,
        ReservationService $reservationService
    ): RedirectResponse {
        try {
            $reservation = $reservationService->confirmHold($token, $request->validated());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('booking.search')
                ->withErrors(['booking' => $exception->getMessage()]);
        }

        return redirect()->route('booking.confirmation', [
            'token' => $reservation->public_token,
        ]);
    }

    public function show(string $token): View
    {
        $reservation = Reservation::query()
            ->where('public_token', $token)
            ->with(['rooms', 'guestLinks.guest'])
            ->firstOrFail();

        return view('booking.confirmation', ['reservation' => $reservation]);
    }
}
