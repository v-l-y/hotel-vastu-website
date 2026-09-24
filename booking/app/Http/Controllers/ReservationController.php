<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use App\Models\Invoice;
use App\Models\Reservation;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function show(string $token): View
    {
        $reservation = Reservation::query()
            ->where('public_token', $token)
            ->with(['rooms', 'guestLinks.guest', 'feedback'])
            ->firstOrFail();

        $invoice = null;
        if ($reservation->status === 'checked_out') {
            $folio = Folio::query()->where('reservation_id', $reservation->id)->first();
            if ($folio !== null) {
                $invoice = Invoice::query()
                    ->where('folio_id', $folio->id)
                    ->latest('id')
                    ->first();
            }
        }

        return view('booking.confirmation', compact('reservation', 'invoice'));
    }
}
