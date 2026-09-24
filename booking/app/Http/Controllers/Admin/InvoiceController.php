<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Services\CustomerMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function show(Request $request, Invoice $invoice): View
    {
        $invoice->load([
            'items',
            'creditNotes.items',
            'creditNotes.refund',
            'folio.payments',
            'restaurantOrder.kitchenTicket',
            'restaurantOrder.payments',
        ]);

        $this->authorizeInvoiceScope($request, $invoice);

        $reservation = $invoice->folio_id !== null
            ? Reservation::query()->findOrFail($invoice->folio->reservation_id)
            : null;
        $restaurantOrder = $invoice->restaurantOrder;
        $payments = $invoice->folio_id !== null
            ? $invoice->folio->payments
            : ($restaurantOrder?->payments ?? collect());

        return view('admin.invoice', [
            'invoice' => $invoice,
            'reservation' => $reservation,
            'restaurantOrder' => $restaurantOrder,
            'payments' => $payments,
            'copyLabel' => 'DUPLICATE FOR SUPPLIER',
        ]);
    }

    public function send(
        Request $request,
        Invoice $invoice,
        CustomerMessageService $messages
    ): RedirectResponse {
        $invoice->loadMissing('restaurantOrder');
        $this->authorizeInvoiceScope($request, $invoice);
        $messages->resendInvoice($invoice);

        return back()->with('status', 'Invoice link sent to the registered mobile/email where available.');
    }

    private function authorizeInvoiceScope(Request $request, Invoice $invoice): void
    {
        $role = (string) ($request->attributes->get('admin_user')?->role ?? '');

        if ($role === 'restaurant' && $invoice->document_type !== 'restaurant') {
            abort(403);
        }
    }
}
