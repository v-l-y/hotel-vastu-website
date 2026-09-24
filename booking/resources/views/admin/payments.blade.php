@extends('admin.layout')
@section('title','Payments & Refunds')
@section('content')
<div class="toolbar">
<div>
<h1>Payments & refunds</h1>
@if(($selectedReservationId ?? 0) > 0)
<p class="muted">Recording payment for the selected reservation. The booking target is already set.</p>
@elseif(($selectedFolioId ?? 0) > 0)
<p class="muted">Recording payment for the selected in-house folio. The folio target is already set.</p>
@endif
</div>
@if(($selectedReservationId ?? 0) > 0 || ($selectedFolioId ?? 0) > 0)
<a class="button-link" href="{{ route('admin.front-desk') }}">← Back to Front Desk</a>
@endif
</div>

@if($showHotelPayments ?? false)
<section class="panel">
<h2>Open hotel balances</h2>
@forelse($folios as $folio)
<form class="grid" method="post" action="{{ route('admin.payments.store') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="target_type" value="folio"><input type="hidden" name="target_id" value="{{ $folio->id }}">
<div><strong>Folio #{{ $folio->id }}</strong><br>Reservation #{{ $folio->reservation_id }} · Balance ₹{{ number_format((float)$folio->balance,2) }}</div>
<label>Method<select name="method"><option>cash</option><option>upi</option><option>card</option><option>bank_transfer</option></select></label>
<label>Amount<input type="number" step="0.01" min="0.01" max="{{ $folio->balance }}" name="amount" value="{{ $folio->balance }}" required></label>
<label>Reference<input name="external_reference"></label>
<div><button>Record payment</button></div>
</form><hr>
@empty<p>No open folio balances.</p>@endforelse
</section>

<section class="panel">
<h2>Pre-arrival reservation payments</h2>
@forelse($reservations as $reservation)
<form class="grid" method="post" action="{{ route('admin.payments.store') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="target_type" value="reservation"><input type="hidden" name="target_id" value="{{ $reservation->id }}">
<div><strong>{{ $reservation->booking_number }}</strong><br>Total ₹{{ number_format((float)$reservation->total,2) }} · {{ str_replace('_',' ',$reservation->payment_status) }}</div>
<label>Method<select name="method"><option>cash</option><option>upi</option><option>card</option><option>bank_transfer</option></select></label>
<label>Amount<input type="number" step="0.01" min="0.01" name="amount" required></label>
<label>Reference<input name="external_reference"></label>
<div><button>Record payment</button></div>
</form><hr>
@empty<p>No confirmed reservations.</p>@endforelse
</section>
@endif

@if($showRestaurantPayments ?? false)
<section class="panel">
<h2>Restaurant balances</h2>
@forelse($restaurantOrders as $order)
<form class="grid" method="post" action="{{ route('admin.payments.store') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="target_type" value="restaurant_order"><input type="hidden" name="target_id" value="{{ $order->id }}">
<div><strong>{{ $order->order_number }}</strong><br>Total ₹{{ number_format((float)$order->total,2) }} · {{ str_replace('_',' ',$order->payment_status) }}</div>
<label>Method<select name="method"><option>cash</option><option>upi</option><option>card</option><option>bank_transfer</option></select></label>
<label>Amount<input type="number" step="0.01" min="0.01" name="amount" required></label>
<label>Reference<input name="external_reference"></label>
<div><button>Record payment</button></div>
</form><hr>
@empty<p>No restaurant balances.</p>@endforelse
</section>
@endif

@if($showHotelPayments ?? false)
<section class="panel">
<h2>Recent invoices</h2>
@forelse($invoices as $invoice)
<p><a href="{{ route('admin.invoices.show',$invoice) }}"><strong>{{ $invoice->invoice_number }}</strong></a> · {{ $invoice->issued_at->format('d M Y, h:i A') }} · ₹{{ number_format((float)$invoice->total,2) }}</p>
@empty<p>No invoices available.</p>@endforelse
</section>
@endif

<section class="panel">
<h2>Recent payments / refunds</h2>
<table><thead><tr><th>Payment</th><th>Target</th><th>Amount</th><th>Refundable</th><th>Refund</th></tr></thead><tbody>
@foreach($payments as $payment)
@php($refunded=(float)$payment->refunds->where('status','succeeded')->sum('amount'))
@php($pendingRefunds=(float)$payment->refunds->where('status','pending')->sum('amount'))
@php($refundable=max(0,(float)$payment->amount-$refunded-$pendingRefunds))
<tr>
<td>#{{ $payment->id }} · {{ $payment->method }}<br><span class="muted">{{ $payment->paid_at?->format('d M Y, h:i A') }}</span></td>
<td>@if($payment->reservation_id)Reservation {{ $payment->reservation?->booking_number }}@elseif($payment->folio_id)Folio #{{ $payment->folio_id }}@else Restaurant {{ $payment->restaurantOrder?->order_number }}@endif</td>
<td>₹{{ number_format((float)$payment->amount,2) }}<br>Refunded ₹{{ number_format($refunded,2) }}@if($pendingRefunds>0)<br>Pending refund ₹{{ number_format($pendingRefunds,2) }}@endif</td>
<td>₹{{ number_format($refundable,2) }}</td>
<td>
@if(($canRefund ?? false) && $refundable>0)
<form class="actions" method="post" action="{{ route('admin.payments.refund',$payment) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="number" step="0.01" min="0.01" max="{{ $refundable }}" name="amount" placeholder="Amount" required><input name="reason" placeholder="Reason"><button class="danger">Refund</button></form>
@elseif($refundable<=0)
Fully refunded
@else
Restricted
@endif
@if($canRefund ?? false)
@foreach($payment->refunds->where('status','pending') as $pendingRefund)
<form class="actions" method="post" action="{{ route('admin.payments.refunds.reconcile',$pendingRefund) }}">@csrf<button type="submit">Reconcile refund #{{ $pendingRefund->id }}</button></form>
@endforeach
@endif
</td>
</tr>
@endforeach
</tbody></table>
</section>
@endsection
