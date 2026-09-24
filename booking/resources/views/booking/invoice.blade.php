<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Invoice {{ $invoice->invoice_number }} | Hotel Vastu Premium</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f6f3ef;color:#241f1b}main{max-width:760px;margin:auto;padding:32px 20px 64px}.panel{background:#fff;border:1px solid #ded8d1;border-radius:16px;padding:24px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid #e2ddd7}th:last-child,td:last-child{text-align:right}
</style>
</head>
<body>
<main>
<p>Hotel Vastu Premium</p>
<h1>Invoice {{ $invoice->invoice_number }}</h1>
<p>Booking {{ $reservation->booking_number }} · Issued {{ $invoice->issued_at->format('d M Y, h:i A') }}</p>
<section class="panel">
<table>
<thead><tr><th>Description</th><th>Total</th></tr></thead>
<tbody>@foreach($invoice->items as $item)<tr><td>{{ $item->description }}</td><td>₹{{ number_format((float)$item->amount,2) }}</td></tr>@endforeach</tbody>
</table>
@if((float)$reservation->discount > 0)
<p><strong>Room charges before discount</strong> ₹{{ number_format((float)$reservation->subtotal,2) }}<br>
Discount −₹{{ number_format((float)$reservation->discount,2) }}
@if($reservation->promotion_code_snapshot) · Promo {{ $reservation->promotion_code_snapshot }}@endif
</p>
@endif
<p><strong>Total ₹{{ number_format((float)$invoice->total,2) }}</strong><br>Paid ₹{{ number_format((float)$invoice->paid,2) }}<br>Balance ₹{{ number_format((float)$invoice->balance,2) }}</p>
</section>
@if($invoice->creditNotes->isNotEmpty())
<section class="panel" style="margin-top:16px">
<h2>Credit notes</h2>
@foreach($invoice->creditNotes as $creditNote)
<p><strong>{{ $creditNote->credit_note_number }}</strong> · ₹{{ number_format((float)$creditNote->amount,2) }} · {{ str_replace('_',' ',$creditNote->refund?->refund_type ?? 'adjustment') }}@if($creditNote->reason) · {{ $creditNote->reason }}@endif</p>
@endforeach
</section>
@endif
</main>
</body>
</html>
