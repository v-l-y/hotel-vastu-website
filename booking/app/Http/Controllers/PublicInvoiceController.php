<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use App\Models\Invoice;
use App\Models\Reservation;
use Illuminate\View\View;

class PublicInvoiceController extends Controller
{
    public function show(string $token): View
    {
        $reservation = Reservation::query()
            ->where('public_token', $token)
            ->where('status', 'checked_out')
            ->firstOrFail();

        $folio = Folio::query()->where('reservation_id', $reservation->id)->firstOrFail();
        $invoice = Invoice::query()
            ->where('folio_id', $folio->id)
            ->with(['items', 'creditNotes.items', 'creditNotes.refund'])
            ->latest('id')
            ->firstOrFail();

        return view('booking.invoice', compact('reservation', 'invoice'));
    }
}
