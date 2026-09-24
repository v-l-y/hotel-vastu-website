@extends('admin.layout')
@section('title','Invoice '.$invoice->invoice_number)
@section('content')
<h1>Invoice {{ $invoice->invoice_number }}</h1>
<section class="panel">
<p>Issued {{ $invoice->issued_at->format('d M Y, h:i A') }}</p>
<table>
<thead><tr><th>Description</th><th>Subtotal</th><th>Tax</th><th>Total</th></tr></thead>
<tbody>
@foreach($invoice->items as $item)
<tr><td>{{ $item->description }}</td><td>₹{{ number_format((float)$item->subtotal,2) }}</td><td>₹{{ number_format((float)$item->tax,2) }}</td><td>₹{{ number_format((float)$item->amount,2) }}</td></tr>
@endforeach
</tbody>
</table>
<p><strong>Total ₹{{ number_format((float)$invoice->total,2) }}</strong><br>Paid at issue ₹{{ number_format((float)$invoice->paid,2) }}<br>Balance at issue ₹{{ number_format((float)$invoice->balance,2) }}</p>
</section>

@if($invoice->creditNotes->isNotEmpty())
<section class="panel">
<h2>Credit notes / post-invoice refunds</h2>
@foreach($invoice->creditNotes as $creditNote)
<p><strong>{{ $creditNote->credit_note_number }}</strong> · {{ $creditNote->issued_at->format('d M Y, h:i A') }} · ₹{{ number_format((float)$creditNote->amount,2) }}@if($creditNote->reason) · {{ $creditNote->reason }}@endif</p>
@endforeach
</section>
@endif
@endsection
