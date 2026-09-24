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
            'bookingNumber' => $reservation->booking_number,
        ]);
    }

    public function show(string $bookingNumber): View
    {
        $reservation = Reservation::query()
            ->where('booking_number', $bookingNumber)
            ->with(['rooms', 'guestLinks.guest'])
            ->firstOrFail();

        return view('booking.confirmation', ['reservation' => $reservation]);
    }
}
