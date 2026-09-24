<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\Invoice;
use App\Models\Reservation;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice): View
    {
        $folio = Folio::query()->findOrFail($invoice->folio_id);
        $reservation = Reservation::query()->findOrFail($folio->reservation_id);

        return view('admin.invoice', [
            'invoice' => $invoice->load(['items', 'creditNotes.items', 'creditNotes.refund']),
            'reservation' => $reservation,
        ]);
    }
}
