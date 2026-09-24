@php
$documentTitle = $invoice->is_gst_invoice ? 'TAX INVOICE' : 'INVOICE / RECEIPT';
$contextNumber = $reservation?->booking_number ?? $restaurantOrder?->order_number;
$kotNumber = $restaurantOrder?->kitchenTicket?->ticket_number;
$paymentRows = collect($payments ?? [])->where('status', 'succeeded');
$paymentSummary = $paymentRows
    ->groupBy('method')
    ->map(fn ($rows, $method) => [
        'method' => strtoupper(str_replace('_', ' ', $method)),
        'amount' => (float) $rows->sum('amount'),
    ])
    ->values();
@endphp

@push('styles')
<style>
.invoice-page{max-width:1050px;margin:0 auto}.invoice-actions{display:flex;gap:10px;justify-content:flex-end;align-items:center;flex-wrap:wrap;margin-bottom:14px}.invoice-sheet{background:#fff;border:1px solid #cfc7bf;color:#181512}.invoice-top{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(260px,.65fr);border-bottom:2px solid #181512}.invoice-supplier,.invoice-doc{padding:22px}.invoice-doc{border-left:1px solid #bdb5ad}.invoice-supplier h1{font-size:1.5rem;margin:0 0 6px}.invoice-supplier p,.invoice-doc p{margin:4px 0;line-height:1.45}.invoice-title{font-size:1.2rem;font-weight:900;letter-spacing:.1em;margin-bottom:12px}.invoice-copy{font-size:.72rem;font-weight:900;letter-spacing:.09em;color:#655d56}.invoice-meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));border-bottom:1px solid #bdb5ad}.invoice-meta-box{padding:16px 20px;min-width:0}.invoice-meta-box+ .invoice-meta-box{border-left:1px solid #bdb5ad}.invoice-meta-box h2{font-size:.76rem;letter-spacing:.08em;text-transform:uppercase;margin:0 0 8px;color:#655d56}.invoice-meta-box p{margin:3px 0;line-height:1.45}.invoice-table-wrap{overflow:auto}.invoice-table{width:100%;border-collapse:collapse}.invoice-table th,.invoice-table td{border-right:1px solid #d8d1ca;border-bottom:1px solid #d8d1ca;padding:9px 8px;font-size:.86rem;vertical-align:top}.invoice-table th{background:#f3f0ed;color:#302a25;position:static}.invoice-table th:last-child,.invoice-table td:last-child{border-right:0}.invoice-money{text-align:right;white-space:nowrap}.invoice-center{text-align:center;white-space:nowrap}.invoice-footer-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.65fr)}.invoice-notes{padding:18px 20px;border-right:1px solid #bdb5ad}.invoice-totals{padding:18px 20px}.invoice-total-row{display:flex;justify-content:space-between;gap:18px;padding:4px 0}.invoice-total-row.grand{border-top:2px solid #181512;margin-top:7px;padding-top:9px;font-size:1.08rem;font-weight:900}.invoice-small{font-size:.82rem;color:#5e5650}.invoice-payment-list{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.invoice-payment-pill{border:1px solid #ccc3bb;border-radius:999px;padding:4px 8px;font-size:.78rem}.invoice-signature{margin-top:28px;text-align:right}.invoice-credit{border-top:1px solid #bdb5ad;padding:16px 20px}.invoice-credit h2{font-size:.9rem;margin:0 0 8px}.invoice-credit p{margin:5px 0}.invoice-compliance-note{padding:11px 20px;border-top:1px solid #bdb5ad;font-size:.75rem;color:#655d56;text-align:center}
@media(max-width:720px){.invoice-top,.invoice-meta-grid,.invoice-footer-grid{grid-template-columns:1fr}.invoice-doc,.invoice-meta-box+ .invoice-meta-box{border-left:0;border-top:1px solid #bdb5ad}.invoice-notes{border-right:0;border-bottom:1px solid #bdb5ad}.invoice-sheet{border-radius:0}.invoice-supplier,.invoice-doc,.invoice-meta-box,.invoice-notes,.invoice-totals{padding:14px}}
@media print{
@page{size:A4;margin:10mm}
body{background:#fff!important;color:#000!important}
.admin-sidebar,.admin-topbar,.invoice-actions,.notice,.error,[data-toast-root]{display:none!important}
.admin-app,.admin-main{display:block!important;min-height:0!important}
.admin-content{max-width:none!important;padding:0!important;margin:0!important}
.invoice-page{max-width:none!important;margin:0!important}
.invoice-sheet{border:1px solid #000!important}
.invoice-table-wrap{overflow:visible!important}
.invoice-table th,.invoice-table td{font-size:9pt;padding:5px}
.invoice-supplier,.invoice-doc,.invoice-meta-box,.invoice-notes,.invoice-totals{padding:10px 12px}
a{text-decoration:none!important;color:#000!important}
}
</style>
@endpush

<div class="invoice-page">
<div class="invoice-actions">
<button type="button" onclick="window.print()">Print / Save PDF</button>
@if(request()->routeIs('admin.*'))
<form method="post" action="{{ route('admin.invoices.send', $invoice) }}">
@csrf
<button type="submit">Send bill to customer</button>
</form>
@endif
</div>

<article class="invoice-sheet" aria-label="{{ $documentTitle }} {{ $invoice->invoice_number }}">
<header class="invoice-top">
<div class="invoice-supplier">
<h1>{{ $invoice->supplier_name ?: config('billing.legal_name','Hotel Vastu Premium') }}</h1>
@if($invoice->supplier_address)<p>{{ $invoice->supplier_address }}</p>@endif
@if($invoice->supplier_state)<p>{{ $invoice->supplier_state }}@if($invoice->supplier_state_code) · State Code {{ $invoice->supplier_state_code }}@endif</p>@endif
@if($invoice->supplier_gstin)<p><strong>GSTIN:</strong> {{ $invoice->supplier_gstin }}</p>@endif
@if($invoice->supplier_phone || $invoice->supplier_email)
<p>@if($invoice->supplier_phone){{ $invoice->supplier_phone }}@endif @if($invoice->supplier_phone && $invoice->supplier_email) · @endif @if($invoice->supplier_email){{ $invoice->supplier_email }}@endif</p>
@endif
</div>
<div class="invoice-doc">
<div class="invoice-copy">{{ $copyLabel ?? 'ORIGINAL FOR RECIPIENT' }}</div>
<div class="invoice-title">{{ $documentTitle }}</div>
<p><strong>Invoice No:</strong> {{ $invoice->invoice_number }}</p>
<p><strong>Date:</strong> {{ $invoice->issued_at->format('d M Y, h:i A') }}</p>
@if($contextNumber)<p><strong>{{ $reservation ? 'Booking' : 'Order' }}:</strong> {{ $contextNumber }}</p>@endif
@if($kotNumber)<p><strong>KOT:</strong> {{ $kotNumber }}</p>@endif
</div>
</header>

<section class="invoice-meta-grid">
<div class="invoice-meta-box">
<h2>Bill To</h2>
<p><strong>{{ $invoice->recipient_name ?: 'Customer' }}</strong></p>
@if($invoice->recipient_address)<p>{{ $invoice->recipient_address }}</p>@endif
@if($invoice->recipient_state)<p>{{ $invoice->recipient_state }}@if($invoice->recipient_state_code) · State Code {{ $invoice->recipient_state_code }}@endif</p>@endif
@if($invoice->recipient_gstin)<p><strong>GSTIN:</strong> {{ $invoice->recipient_gstin }}</p>@endif
@if($invoice->recipient_phone)<p>Mobile: {{ $invoice->recipient_phone }}</p>@endif
@if($invoice->recipient_email)<p>Email: {{ $invoice->recipient_email }}</p>@endif
</div>
<div class="invoice-meta-box">
<h2>Supply Details</h2>
<p><strong>Place of Supply:</strong> {{ $invoice->place_of_supply ?: '—' }}@if($invoice->place_of_supply_code) ({{ $invoice->place_of_supply_code }})@endif</p>
<p><strong>Reverse Charge:</strong> {{ $invoice->reverse_charge ? 'Yes' : 'No' }}</p>
@if($reservation)
<p><strong>Stay:</strong> {{ $reservation->check_in_date->format('d M Y') }} → {{ $reservation->check_out_date->format('d M Y') }}</p>
@elseif($restaurantOrder)
<p><strong>Order type:</strong> {{ ucfirst(str_replace('_',' ',$restaurantOrder->order_type)) }}</p>
@endif
</div>
</section>

<div class="invoice-table-wrap">
<table class="invoice-table">
<thead>
<tr>
<th>Description</th>
<th class="invoice-center">SAC</th>
<th class="invoice-center">Qty</th>
<th class="invoice-money">Taxable</th>
<th class="invoice-center">Tax %</th>
@if($invoice->is_gst_invoice)
<th class="invoice-money">CGST</th>
<th class="invoice-money">SGST</th>
<th class="invoice-money">IGST</th>
@else
<th class="invoice-money">Tax / Levy</th>
@endif
<th class="invoice-money">Total</th>
</tr>
</thead>
<tbody>
@foreach($invoice->items as $item)
<tr>
<td>{{ $item->description }}<br><span class="invoice-small">{{ ucfirst(str_replace('_',' ',$item->category)) }}</span></td>
<td class="invoice-center">{{ $item->sac_code ?: '—' }}</td>
<td class="invoice-center">{{ $item->quantity }}</td>
<td class="invoice-money">₹{{ number_format((float)$item->subtotal,2) }}</td>
<td class="invoice-center">{{ rtrim(rtrim(number_format((float)$item->tax_rate_percent,4,'.',''),'0'),'.') ?: '0' }}%</td>
@if($invoice->is_gst_invoice)
<td class="invoice-money">₹{{ number_format((float)$item->cgst_amount,2) }}</td>
<td class="invoice-money">₹{{ number_format((float)$item->sgst_amount,2) }}</td>
<td class="invoice-money">₹{{ number_format((float)$item->igst_amount,2) }}</td>
@else
<td class="invoice-money">₹{{ number_format((float)$item->tax,2) }}</td>
@endif
<td class="invoice-money"><strong>₹{{ number_format((float)$item->amount,2) }}</strong></td>
</tr>
@endforeach
</tbody>
</table>
</div>

<section class="invoice-footer-grid">
<div class="invoice-notes">
<strong>Payment details</strong>
@if($paymentSummary->isEmpty())
<p class="invoice-small">No successful payment line is attached to this document.</p>
@else
<div class="invoice-payment-list">
@foreach($paymentSummary as $row)
<span class="invoice-payment-pill">{{ $row['method'] }} · ₹{{ number_format($row['amount'],2) }}</span>
@endforeach
</div>
@endif

@if($invoice->creditNotes->isNotEmpty())
<div style="margin-top:16px">
<strong>Post-invoice credit notes</strong>
@foreach($invoice->creditNotes as $creditNote)
<p class="invoice-small">{{ $creditNote->credit_note_number }} · {{ $creditNote->issued_at->format('d M Y') }} · ₹{{ number_format((float)$creditNote->amount,2) }}@if($creditNote->reason) · {{ $creditNote->reason }}@endif</p>
@endforeach
</div>
@endif

<div class="invoice-signature">
@if(config('billing.authorised_signatory'))
<strong>{{ config('billing.authorised_signatory') }}</strong><br>
<span class="invoice-small">Authorised Signatory</span>
@else
<strong>For {{ $invoice->supplier_name ?: config('billing.legal_name','Hotel Vastu Premium') }}</strong><br>
<span class="invoice-small">Electronically generated document</span>
@endif
</div>
</div>

<div class="invoice-totals">
<div class="invoice-total-row"><span>Taxable value</span><span>₹{{ number_format((float)$invoice->subtotal,2) }}</span></div>
@if($invoice->is_gst_invoice)
<div class="invoice-total-row"><span>CGST</span><span>₹{{ number_format((float)$invoice->cgst,2) }}</span></div>
<div class="invoice-total-row"><span>SGST</span><span>₹{{ number_format((float)$invoice->sgst,2) }}</span></div>
<div class="invoice-total-row"><span>IGST</span><span>₹{{ number_format((float)$invoice->igst,2) }}</span></div>
@else
<div class="invoice-total-row"><span>Tax / levy</span><span>₹{{ number_format((float)$invoice->tax,2) }}</span></div>
@endif
<div class="invoice-total-row grand"><span>Grand Total</span><span>₹{{ number_format((float)$invoice->total,2) }}</span></div>
<div class="invoice-total-row"><span>Paid</span><span>₹{{ number_format((float)$invoice->paid,2) }}</span></div>
<div class="invoice-total-row"><span>Balance</span><span>₹{{ number_format((float)$invoice->balance,2) }}</span></div>
</div>
</section>

<footer class="invoice-compliance-note">
@if($invoice->is_gst_invoice)
GST invoice particulars are rendered from the legal/tax identity snapshot captured when this invoice was issued.
@else
This document is not labelled as a GST Tax Invoice because a GST registration was not captured for the supplier at issue time.
@endif
</footer>
</article>
</div>
