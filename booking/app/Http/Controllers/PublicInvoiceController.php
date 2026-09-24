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
        $invoice = Invoice::query()
            ->where('public_token', $token)
            ->with([
                'items',
                'creditNotes.items',
                'creditNotes.refund',
                'folio.payments',
                'restaurantOrder.kitchenTicket',
                'restaurantOrder.payments',
            ])
            ->firstOrFail();

        return $this->render($invoice, 'ORIGINAL FOR RECIPIENT');
    }

    public function showForReservation(string $token): View
    {
        $reservation = Reservation::query()
            ->where('public_token', $token)
            ->where('status', 'checked_out')
            ->firstOrFail();

        $folio = Folio::query()->where('reservation_id', $reservation->id)->firstOrFail();
        $invoice = Invoice::query()
            ->where('folio_id', $folio->id)
            ->with([
                'items',
                'creditNotes.items',
                'creditNotes.refund',
                'folio.payments',
            ])
            ->latest('id')
            ->firstOrFail();

        return $this->render($invoice, 'ORIGINAL FOR RECIPIENT');
    }

    private function render(Invoice $invoice, string $copyLabel): View
    {
        $reservation = $invoice->folio_id !== null
            ? Reservation::query()->findOrFail($invoice->folio->reservation_id)
            : null;
        $restaurantOrder = $invoice->restaurantOrder;

        $payments = $invoice->folio_id !== null
            ? $invoice->folio->payments
            : ($restaurantOrder?->payments ?? collect());

        return view('booking.invoice', compact(
            'invoice',
            'reservation',
            'restaurantOrder',
            'payments',
            'copyLabel'
        ));
    }
}
