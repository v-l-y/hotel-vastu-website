<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    public function createFromFolio(Folio $folio): Invoice
    {
        return DB::transaction(function () use ($folio) {
            $existing = Invoice::query()->where('folio_id', $folio->id)->first();
            if ($existing !== null) {
                return $existing->load('items');
            }

            $folio->load('charges');

            $invoice = Invoice::query()->create([
                'invoice_number' => 'HVI-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'folio_id' => $folio->id,
                'subtotal' => round((float) $folio->charges->sum('subtotal'), 2),
                'tax' => round((float) $folio->charges->sum('tax'), 2),
                'total' => (float) $folio->charges_total,
                'paid' => round((float) $folio->payments_total - (float) $folio->refunds_total, 2),
                'balance' => (float) $folio->balance,
                'issued_at' => now(),
            ]);

            foreach ($folio->charges as $charge) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'category' => $charge->category,
                    'description' => $charge->description,
                    'quantity' => $charge->quantity,
                    'subtotal' => $charge->subtotal,
                    'tax' => $charge->tax,
                    'amount' => $charge->amount,
                ]);
            }

            return $invoice->load('items');
        }, 3);
    }
}
