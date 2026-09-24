<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;

class CreditNoteService
{
    public function __construct(private BillingDocumentNumberService $numbers)
    {
    }

    public function createForRefund(Payment $payment, Refund $refund): ?CreditNote
    {
        $invoice = null;

        if ($payment->folio_id !== null) {
            $invoice = Invoice::query()->where('folio_id', $payment->folio_id)->first();
        } elseif ($payment->restaurant_order_id !== null) {
            $invoice = Invoice::query()->where('restaurant_order_id', $payment->restaurant_order_id)->first();
        }

        if ($invoice === null) {
            return null;
        }

        $existing = CreditNote::query()->where('refund_id', $refund->id)->first();
        if ($existing !== null) {
            return $existing->load('items');
        }

        $creditNote = CreditNote::query()->create([
            'credit_note_number' => $this->numbers->next((string) config('billing.series.credit_note', 'HC')),
            'invoice_id' => $invoice->id,
            'refund_id' => $refund->id,
            'amount' => $refund->amount,
            'reason' => $refund->reason,
            'issued_at' => now(),
        ]);

        CreditNoteItem::query()->create([
            'credit_note_id' => $creditNote->id,
            'description' => 'Refund against invoice '.$invoice->invoice_number,
            'amount' => $refund->amount,
        ]);

        return $creditNote->load('items');
    }
}
