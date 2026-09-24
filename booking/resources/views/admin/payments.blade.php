@extends('admin.layout')
@section('title','Payments & Refunds')

@push('styles')
<style>
.payments-shell{display:grid;gap:18px}
.payments-hero{background:linear-gradient(135deg,#241b16 0%,#3a2a21 100%);color:#fff;border-radius:20px;padding:22px;display:flex;gap:18px;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;box-shadow:0 12px 30px rgba(35,30,26,.12)}
.payments-hero h1{margin:0 0 6px;font-size:clamp(1.7rem,3vw,2.35rem)}
.payments-hero p{margin:0;color:#e8ddd4;max-width:720px}
.payments-hero .button-link{background:#fff;color:#2b211b;border-color:#fff}
.payments-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.payments-kpi{background:#fff;border:1px solid #e2dbd4;border-radius:16px;padding:16px;position:relative;overflow:hidden}
.payments-kpi:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:#8e7159}
.payments-kpi strong{display:block;font-size:1.75rem;line-height:1.1;margin-top:7px}
.payments-kpi small{display:block;color:#776e66;margin-top:6px}
.payments-section{background:#fff;border:1px solid #e1dad3;border-radius:18px;padding:18px}
.payments-section-head{display:flex;gap:12px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px}
.payments-section-head h2{margin:0 0 4px}
.payments-section-head p{margin:0}
.payments-card-list{display:grid;gap:12px}
.payment-target-card{border:1px solid #e5ddd6;border-radius:16px;padding:16px;background:#fcfaf8}
.payment-target-card.focused{border-color:#a98b72;box-shadow:0 0 0 2px rgba(142,113,89,.1)}
.payment-target-head{display:flex;gap:12px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px}
.payment-target-title{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.payment-target-title strong{font-size:1.08rem}
.payment-target-meta{display:flex;gap:7px 14px;flex-wrap:wrap;color:#6b625b;margin-top:6px}
.payment-amount{font-size:1.35rem;font-weight:800;white-space:nowrap}
.payment-entry-form{display:grid;grid-template-columns:minmax(150px,.7fr) minmax(150px,.7fr) minmax(200px,1fr) auto;gap:10px;align-items:end;padding-top:13px;border-top:1px solid #ece4de}
.payment-entry-form label{min-width:0}
.payment-entry-form input,.payment-entry-form select{width:100%;box-sizing:border-box}
.payment-entry-form button{white-space:nowrap}
.payment-help{display:block;color:#776e66;font-size:.8rem;font-weight:500}
.payments-empty{border:1px dashed #d8cfc7;border-radius:14px;padding:22px;text-align:center;background:#fcfaf8;color:#6d655e}
.payments-invoice-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.payments-invoice{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px;border:1px solid #ece4de;border-radius:13px;background:#fcfaf8}
.payments-invoice a{text-decoration:none}
.payments-ledger{display:grid;gap:10px}
.payment-ledger-card{border:1px solid #e5ddd6;border-radius:15px;padding:15px;background:#fff}
.payment-ledger-head{display:flex;gap:12px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap}
.payment-ledger-title{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.payment-ledger-title strong{font-size:1.02rem}
.payment-ledger-meta{display:flex;gap:7px 14px;flex-wrap:wrap;margin-top:7px;color:#6c635b;font-size:.92rem}
.payment-ledger-amount{text-align:right}
.payment-ledger-amount strong{display:block;font-size:1.18rem}
.payment-refund-panel{margin-top:12px;padding-top:12px;border-top:1px solid #eee7e1}
.payment-refund-form{display:grid;grid-template-columns:minmax(130px,.55fr) minmax(180px,.75fr) minmax(220px,1fr) auto;gap:8px;align-items:end}
.payment-refund-form input{width:100%;box-sizing:border-box}
.payment-method-badge{text-transform:capitalize}
.payment-context{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.payment-context-note{padding:12px 14px;border-radius:13px;background:#f4efe9;color:#5e554d}
@media(max-width:900px){
.payments-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
.payment-entry-form{grid-template-columns:1fr 1fr}
.payment-entry-form button{width:100%}
.payments-invoice-list{grid-template-columns:1fr}
.payment-refund-form{grid-template-columns:1fr}
}
@media(max-width:640px){
.payments-hero{padding:18px;border-radius:16px}
.payments-kpis,.payment-entry-form{grid-template-columns:1fr}
.payment-target-head,.payment-ledger-head{display:grid;grid-template-columns:1fr}
.payment-amount,.payment-ledger-amount{text-align:left}
}
</style>
@endpush

@section('content')
@php($targetedReservation = ($selectedReservationId ?? 0) > 0)
@php($targetedFolio = ($selectedFolioId ?? 0) > 0)
@php($isTargeted = $targetedReservation || $targetedFolio)

<div class="payments-shell">
<section class="payments-hero">
<div>
<h1>Payments & refunds</h1>
@if($targetedReservation)
<p>Record a verified payment for the selected reservation. The booking target is already set.</p>
@elseif($targetedFolio)
<p>Settle the selected in-house folio. The folio target is already set.</p>
@else
<p>Collect hotel and restaurant balances, review recent payments, and process eligible refunds.</p>
@endif
</div>
@if($isTargeted)
<a class="button-link" href="{{ route('admin.front-desk') }}">← Back to Front Desk</a>
@endif
</section>

@if(!$isTargeted)
<form class="search-bar" method="get" action="{{ route('admin.payments') }}">
<input name="q" value="{{ $search ?? '' }}" placeholder="Search booking, guest or restaurant order" aria-label="Search payment targets">
<button type="submit">Search</button>
@if(($search ?? '') !== '')
<a class="button-link" href="{{ route('admin.payments') }}">Clear</a>
@endif
</form>

<div class="payments-kpis">
@if($showHotelPayments ?? false)
<div class="payments-kpi"><span class="muted">Open folios</span><strong>{{ method_exists($folios,'total') ? $folios->total() : $folios->count() }}</strong><small>In-house balances awaiting settlement</small></div>
<div class="payments-kpi"><span class="muted">Confirmed bookings</span><strong>{{ method_exists($reservations,'total') ? $reservations->total() : $reservations->count() }}</strong><small>Pre-arrival reservations available for payment</small></div>
<div class="payments-kpi"><span class="muted">Recent invoices</span><strong>{{ method_exists($invoices,'total') ? $invoices->total() : $invoices->count() }}</strong><small>Issued hotel invoices</small></div>
@endif
@if($showRestaurantPayments ?? false)
<div class="payments-kpi"><span class="muted">Restaurant balances</span><strong>{{ method_exists($restaurantOrders,'total') ? $restaurantOrders->total() : $restaurantOrders->count() }}</strong><small>Served dine-in / takeaway orders</small></div>
@endif
<div class="payments-kpi"><span class="muted">Recent payments</span><strong>{{ $payments->total() }}</strong><small>Ledger entries in your scope</small></div>
</div>
@else
<div class="payment-context-note">
@if($targetedReservation)
<strong>Focused payment:</strong> only the selected reservation is shown below.
@else
<strong>Focused payment:</strong> only the selected in-house folio is shown below.
@endif
</div>
@endif

@if(($showHotelPayments ?? false) && !$targetedReservation)
<section class="payments-section" id="folio-balances">
<div class="payments-section-head">
<div><h2>Open hotel balances</h2><p class="muted">Settle amounts due or refund in-house credits before checkout.</p></div>
<span class="status-badge">{{ method_exists($folios,'total') ? $folios->total() : $folios->count() }} open</span>
</div>
<div class="payments-card-list">
@forelse($folios as $folio)
@php($folioBalance=(float)$folio->balance)
<article class="payment-target-card {{ $targetedFolio ? 'focused' : '' }}">
<div class="payment-target-head">
<div>
<div class="payment-target-title">
<strong>Folio #{{ $folio->id }}</strong>
@if($folioBalance > 0)<span class="status-badge warn">Open balance</span>@else<span class="status-badge warn">Credit / overpayment</span>@endif
</div>
<div class="payment-target-meta"><span>Reservation #{{ $folio->reservation_id }}</span><span>In-house account</span></div>
</div>
@if($folioBalance > 0)
<div class="payment-amount">₹{{ number_format($folioBalance,2) }} due</div>
@else
<div class="payment-ledger-amount"><strong>₹{{ number_format(abs($folioBalance),2) }} credit</strong><span class="muted">Refund the excess from the payment ledger below.</span></div>
@endif
</div>
@if($folioBalance > 0)
<form class="payment-entry-form" data-payment-entry method="post" action="{{ route('admin.payments.store') }}">
@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="target_type" value="folio">
<input type="hidden" name="target_id" value="{{ $folio->id }}">
<label>Method
<select name="method" required>
<option value="cash">Cash</option>
<option value="upi">UPI</option>
<option value="card">Card</option>
<option value="bank_transfer">Bank transfer</option>
</select>
</label>
<label>Amount
<input type="number" step="0.01" min="0.01" max="{{ $folioBalance }}" name="amount" value="{{ number_format($folioBalance,2,'.','') }}" inputmode="decimal" required>
</label>
<label>Reference <span class="payment-help">Optional for cash; required for UPI/card/bank transfer.</span>
<input name="external_reference" maxlength="190" autocomplete="off" placeholder="Transaction / receipt reference">
</label>
<button type="submit">Record payment</button>
</form>
@else
<div class="error"><strong>Overpayment requires refund.</strong> Checkout stays blocked until the folio balance returns to ₹0.00.</div>
@endif
</article>
@empty
<div class="payments-empty">No open folio balances.</div>
@endforelse
</div>
@if(method_exists($folios,'links'))
@include('admin.partials.pagination',['paginator'=>$folios,'label'=>'Open folio pagination','fragment'=>'folio-balances'])
@endif
</section>
@endif

@if(($showHotelPayments ?? false) && !$targetedFolio)
<section class="payments-section" id="reservation-balances">
<div class="payments-section-head">
<div><h2>Pre-arrival reservation payments</h2><p class="muted">Collect full or partial payment against confirmed priced reservations.</p></div>
<span class="status-badge">{{ method_exists($reservations,'total') ? $reservations->total() : $reservations->count() }} booking(s)</span>
</div>
<div class="payments-card-list">
@forelse($reservations as $reservation)
@php($reservationDue=(float)($reservationOutstanding[$reservation->id] ?? 0))
@php($reservationPaid=(float)($reservationNetPaid[$reservation->id] ?? 0))
@php($reservationExcess=(float)($reservationOverpaid[$reservation->id] ?? 0))
<article class="payment-target-card {{ $targetedReservation ? 'focused' : '' }}">
<div class="payment-target-head">
<div>
<div class="payment-target-title">
<strong>{{ $reservation->booking_number }}</strong>
<span class="status-badge {{ $reservation->payment_status === 'paid' ? 'good' : ($reservation->payment_status === 'partially_paid' ? 'warn' : '') }}">{{ str_replace('_',' ',$reservation->payment_status) }}</span>
</div>
<div class="payment-target-meta">
<span>Check-in {{ $reservation->check_in_date->format('d M Y') }}</span>
<span>Check-out {{ $reservation->check_out_date->format('d M Y') }}</span>
<span>Room ₹{{ number_format((float)$reservation->subtotal,2) }}</span>
@if((float)$reservation->discount > 0)<span>Discount −₹{{ number_format((float)$reservation->discount,2) }}</span>@endif
<span>Tax ₹{{ number_format((float)$reservation->tax,2) }}</span>
</div>
</div>
<div class="payment-ledger-amount"><strong>₹{{ number_format($reservationDue,2) }} due</strong><span class="muted">Total ₹{{ number_format((float)$reservation->total,2) }} · Net paid ₹{{ number_format($reservationPaid,2) }}</span></div>
</div>
@if($reservationExcess > 0)
<div class="error"><strong>Overpayment ₹{{ number_format($reservationExcess,2) }}.</strong> Refund the excess from the payment ledger below.</div>
@elseif($reservationDue <= 0.009)
<div class="payment-context-note"><strong>Payment complete.</strong> No additional manual payment is required for this reservation.</div>
@else
<form class="payment-entry-form" data-payment-entry method="post" action="{{ route('admin.payments.store') }}">
@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="target_type" value="reservation">
<input type="hidden" name="target_id" value="{{ $reservation->id }}">
<label>Method
<select name="method" required>
<option value="cash">Cash</option>
<option value="upi">UPI</option>
<option value="card">Card</option>
<option value="bank_transfer">Bank transfer</option>
</select>
</label>
<label>Amount
<input type="number" step="0.01" min="0.01" max="{{ $reservationDue }}" name="amount" value="{{ number_format($reservationDue,2,'.','') }}" inputmode="decimal" required>
</label>
<label>Reference <span class="payment-help">Optional for cash; required for UPI/card/bank transfer.</span>
<input name="external_reference" maxlength="190" autocomplete="off" placeholder="Transaction / receipt reference">
</label>
<button type="submit">Record payment</button>
</form>
@endif
</article>
@empty
<div class="payments-empty">No confirmed reservations.</div>
@endforelse
</div>
@if(method_exists($reservations,'links'))
@include('admin.partials.pagination',['paginator'=>$reservations,'label'=>'Reservation payment pagination','fragment'=>'reservation-balances'])
@endif
</section>
@endif

@if(($showRestaurantPayments ?? false) && !$isTargeted)
<section class="payments-section" id="restaurant-balances">
<div class="payments-section-head">
<div><h2>Restaurant balances</h2><p class="muted">Collect direct payment for served dine-in and takeaway orders.</p></div>
<span class="status-badge">{{ method_exists($restaurantOrders,'total') ? $restaurantOrders->total() : $restaurantOrders->count() }} order(s)</span>
</div>
<div class="payments-card-list">
@forelse($restaurantOrders as $order)
@php($orderDue=(float)($restaurantOutstanding[$order->id] ?? 0))
<article class="payment-target-card">
<div class="payment-target-head">
<div>
<div class="payment-target-title"><strong>{{ $order->order_number }}</strong><span class="status-badge {{ $order->payment_status === 'paid' ? 'good' : 'warn' }}">{{ str_replace('_',' ',$order->payment_status) }}</span></div>
<div class="payment-target-meta"><span>{{ str_replace('_',' ',$order->order_type) }}</span><span>Served order</span></div>
</div>
<div class="payment-ledger-amount"><strong>₹{{ number_format($orderDue,2) }} due</strong><span class="muted">Total ₹{{ number_format((float)$order->total,2) }}</span></div>
</div>
<form class="payment-entry-form" data-payment-entry method="post" action="{{ route('admin.payments.store') }}">
@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="target_type" value="restaurant_order">
<input type="hidden" name="target_id" value="{{ $order->id }}">
<label>Method
<select name="method" required>
<option value="cash">Cash</option>
<option value="upi">UPI</option>
<option value="card">Card</option>
<option value="bank_transfer">Bank transfer</option>
</select>
</label>
<label>Amount
<input type="number" step="0.01" min="0.01" max="{{ $orderDue }}" name="amount" value="{{ number_format($orderDue,2,'.','') }}" inputmode="decimal" required>
</label>
<label>Reference <span class="payment-help">Optional for cash; required for UPI/card/bank transfer.</span>
<input name="external_reference" maxlength="190" autocomplete="off" placeholder="Transaction / receipt reference">
</label>
<button type="submit">Record payment</button>
</form>
</article>
@empty
<div class="payments-empty">No restaurant balances.</div>
@endforelse
</div>
@if(method_exists($restaurantOrders,'links'))
@include('admin.partials.pagination',['paginator'=>$restaurantOrders,'label'=>'Restaurant balance pagination','fragment'=>'restaurant-balances'])
@endif
</section>
@endif

@if(($showHotelPayments ?? false) && !$isTargeted)
<section class="payments-section" id="invoice-history">
<div class="payments-section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'table'])
<div><h2>Recent invoices</h2><p class="muted">Issued hotel invoices, newest first.</p></div>
</div>
<span class="status-badge">{{ $invoices->total() }} total</span>
</div>
@if($invoices->count() === 0)
<div class="payments-empty">No invoices available.</div>
@else
<div class="table-wrap">
<table>
<thead><tr><th>Invoice</th><th>Issued</th><th>Total</th><th>Paid</th><th>Balance</th><th>Action</th></tr></thead>
<tbody>
@foreach($invoices as $invoice)
<tr>
<td><span class="table-primary">{{ $invoice->invoice_number }}</span></td>
<td>{{ $invoice->issued_at->format('d M Y, h:i A') }}</td>
<td>₹{{ number_format((float)$invoice->total,2) }}</td>
<td>₹{{ number_format((float)$invoice->paid,2) }}</td>
<td><span class="status-badge {{ (float)$invoice->balance <= 0.009 ? 'good' : 'warn' }}">₹{{ number_format((float)$invoice->balance,2) }}</span></td>
<td><a class="button-link" href="{{ route('admin.invoices.show',$invoice) }}">Open invoice</a></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$invoices,'label'=>'Invoice history pagination','fragment'=>'invoice-history'])
@endif
</section>
@endif

<section class="payments-section" id="payment-history">
<div class="payments-section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'payments'])
<div><h2>Recent payments / refunds</h2><p class="muted">Successful payments and refund activity available to your role.</p></div>
</div>
<span class="status-badge">{{ $payments->total() }} record(s)</span>
</div>

@if($payments->count() === 0)
<div class="payments-empty">No recent payments available.</div>
@else
<div class="table-wrap">
<table>
<thead>
<tr><th>Payment</th><th>Target</th><th>Amount</th><th>Refund state</th><th>Paid at</th><th>Actions</th></tr>
</thead>
<tbody>
@foreach($payments as $payment)
@php($refunded=(float)$payment->refunds->where('status','succeeded')->sum('amount'))
@php($pendingRefunds=(float)$payment->refunds->whereIn('status',['pending','pending_manual'])->sum('amount'))
@php($refundable=max(0,(float)$payment->amount-$refunded-$pendingRefunds))
<tr>
<td>
<span class="table-primary">Payment #{{ $payment->id }}</span>
<span class="table-secondary">{{ ucfirst(str_replace('_',' ',$payment->method)) }}</span>
@if($payment->external_reference)
<span class="table-secondary">Ref {{ $payment->external_reference }}</span>
@endif
</td>
<td>
@if($payment->reservation_id)
<span class="table-primary">Reservation</span>
<span class="table-secondary">{{ $payment->reservation?->booking_number ?? '#'.$payment->reservation_id }}</span>
@elseif($payment->folio_id)
<span class="table-primary">Folio #{{ $payment->folio_id }}</span>
@else
<span class="table-primary">Restaurant</span>
<span class="table-secondary">{{ $payment->restaurantOrder?->order_number ?? 'Order' }}</span>
@endif
</td>
<td>
<span class="table-primary">₹{{ number_format((float)$payment->amount,2) }}</span>
<span class="table-secondary">Refundable ₹{{ number_format($refundable,2) }}</span>
</td>
<td>
@if($refunded > 0)
<span class="status-badge warn">₹{{ number_format($refunded,2) }} refunded</span>
@elseif($pendingRefunds > 0)
<span class="status-badge warn">Pending refund ₹{{ number_format($pendingRefunds,2) }}</span>
@else
<span class="status-badge good">No refund</span>
@endif
</td>
<td>{{ $payment->paid_at?->format('d M Y, h:i A') ?? '—' }}</td>
<td>
<details class="action-menu">
<summary>Manage</summary>
<div class="payment-refund-panel" style="margin-top:0;padding-top:0;border-top:0">
<div class="payment-context">
<span class="muted">Status</span>
<span class="status-badge {{ $payment->status === 'succeeded' ? 'good' : 'warn' }}">{{ ucfirst($payment->status) }}</span>
</div>

@if(($canRefund ?? false) && $refundable>0)
<form class="payment-refund-form" method="post" action="{{ route('admin.payments.refund',$payment) }}" style="grid-template-columns:1fr;margin-top:10px">
@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<label>Refund amount<input type="number" step="0.01" min="0.01" max="{{ $refundable }}" name="amount" inputmode="decimal" placeholder="Amount" required></label>
<label>Refund type
<select name="refund_type" required>
<option value="">Choose refund type</option>
<option value="overpayment">Overpayment return</option>
<option value="duplicate_payment">Duplicate payment</option>
<option value="cancellation">Cancellation</option>
@if($payment->folio_id !== null && in_array((int)$payment->folio_id,$invoicedFolioIds ?? [],true))
<option value="rate_adjustment">Rate adjustment</option>
<option value="service_recovery">Service recovery</option>
@endif
<option value="other">Other</option>
</select>
<span class="payment-help">Rate/service adjustments are available only after invoice and create a credit note.</span>
</label>
<label>Reason<input name="reason" maxlength="255" minlength="3" placeholder="Why this refund is being issued" required></label>
<button class="danger" type="submit">Issue refund</button>
</form>
@elseif(!($canRefund ?? false) && $refundable>0)
<p class="muted">Refunds are restricted for this role.</p>
@elseif($refundable<=0)
<p class="muted">This payment has no refundable balance.</p>
@endif

@if($payment->refunds->isNotEmpty())
<div style="margin-top:12px">
<strong>Refund history</strong>
@foreach($payment->refunds as $refund)
<div class="table-secondary">#{{ $refund->id }} · {{ str_replace('_',' ',$refund->refund_type ?? 'other') }} · ₹{{ number_format((float)$refund->amount,2) }} · {{ str_replace('_',' ',$refund->status) }}</div>
@endforeach
</div>
@endif

@if($canRefund ?? false)
@foreach($payment->refunds->where('status','pending_manual') as $pendingRefund)
<form class="grid" method="post" action="{{ route('admin.payments.refunds.confirm-manual',$pendingRefund) }}" style="grid-template-columns:1fr;margin-top:10px">
@csrf
<label>External refund reference<input name="external_reference" maxlength="190" placeholder="UPI / card / bank refund reference" required></label>
<span class="status-badge warn">₹{{ number_format((float)$pendingRefund->amount,2) }} pending manual confirmation</span>
<button type="submit">Confirm refunded</button>
</form>
@endforeach
@foreach($payment->refunds->where('status','pending') as $pendingRefund)
<form class="actions" method="post" action="{{ route('admin.payments.refunds.reconcile',$pendingRefund) }}" style="margin-top:10px">
@csrf
<button type="submit">Reconcile refund #{{ $pendingRefund->id }}</button>
<span class="status-badge warn">Provider pending</span>
</form>
@endforeach
@endif
</div>
</details>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$payments,'label'=>'Payment history pagination','fragment'=>'payment-history'])
@endif
</section>
</div>
<script>
(() => {
    document.querySelectorAll('[data-payment-entry]').forEach((form) => {
        const method = form.querySelector('select[name="method"]');
        const reference = form.querySelector('input[name="external_reference"]');
        if (!method || !reference) return;

        const syncReference = () => {
            reference.required = method.value !== 'cash';
        };
        method.addEventListener('change', syncReference);
        syncReference();
    });
})();
</script>
@endsection
