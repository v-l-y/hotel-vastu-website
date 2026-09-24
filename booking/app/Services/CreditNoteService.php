<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Str;

class CreditNoteService
{
    public function createForRefund(Payment $payment, Refund $refund): ?CreditNote
    {
        if (! in_array($refund->refund_type, ['cancellation', 'rate_adjustment', 'service_recovery'], true)) {
            return null;
        }

        if ($payment->folio_id === null) {
            return null;
        }

        $invoice = Invoice::query()->where('folio_id', $payment->folio_id)->first();
        if ($invoice === null) {
            return null;
        }

        $existing = CreditNote::query()->where('refund_id', $refund->id)->first();
        if ($existing !== null) {
            return $existing->load('items');
        }

        $creditNote = CreditNote::query()->create([
            'credit_note_number' => 'HVCN-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
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
