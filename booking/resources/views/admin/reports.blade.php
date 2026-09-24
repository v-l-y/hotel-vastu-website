@extends('admin.layout')
@section('title','Reports')
@section('content')
<section class="page-hero">
<div><h1>Reports</h1><p>Review room performance, payments, refunds and served restaurant sales for a selected date range.</p></div>
</section>

<section class="panel">
<div class="section-head"><div><h2>Reporting period</h2><p class="muted">Dates are inclusive.</p></div></div>
<form class="grid" method="get">
<label>From<input type="date" name="from" value="{{ $from->toDateString() }}"></label>
<label>To<input type="date" name="to" value="{{ $to->toDateString() }}"></label>
<div><button>Apply range</button></div>
</form>
</section>

<div class="metric-grid">
<div class="stat-card"><span class="muted">Occupancy</span><strong>{{ number_format($occupancyPercent,2) }}%</strong><small>Booked room-night occupancy</small></div>
<div class="stat-card"><span class="muted">ADR</span><strong>₹{{ number_format($adr,2) }}</strong><small>Average daily rate, pre-tax</small></div>
<div class="stat-card"><span class="muted">Room revenue</span><strong>₹{{ number_format($roomRevenue,2) }}</strong><small>Pre-tax room revenue</small></div>
<div class="stat-card"><span class="muted">Restaurant sales</span><strong>₹{{ number_format($restaurantSales,2) }}</strong><small>Served restaurant sales</small></div>
<div class="stat-card"><span class="muted">Payments</span><strong>₹{{ number_format($paymentsTotal,2) }}</strong><small>Successful payments</small></div>
<div class="stat-card"><span class="muted">Refunds</span><strong>₹{{ number_format($refundsTotal,2) }}</strong><small>Succeeded refunds</small></div>
<div class="stat-card"><span class="muted">Invoice tax</span><strong>₹{{ number_format($taxTotal,2) }}</strong><small>Tax on issued invoices</small></div>
<div class="stat-card"><span class="muted">Bookings created</span><strong>{{ $bookingsCreated }}</strong><small>Reservations created in range</small></div>
</div>

<section class="panel">
<div class="section-head"><div><h2>Payment method split</h2><p class="muted">Successful payments grouped by method.</p></div></div>
@if($paymentsByMethod->isEmpty())
<div class="empty-state">No successful payments in this period.</div>
@else
<div class="table-wrap"><table><thead><tr><th>Method</th><th>Total</th></tr></thead><tbody>
@foreach($paymentsByMethod as $row)<tr><td>{{ str_replace('_',' ',$row->method) }}</td><td>₹{{ number_format((float)$row->total,2) }}</td></tr>@endforeach
</tbody></table></div>
@endif
</section>

<section class="panel">
<div class="section-head"><div><h2>Restaurant item sales</h2><p class="muted">Top served items by quantity in this period.</p></div></div>
@if($topRestaurantItems->isEmpty())
<div class="empty-state">No served restaurant items in this period.</div>
@else
<div class="table-wrap"><table><thead><tr><th>Item</th><th>Quantity</th><th>Pre-tax sales</th></tr></thead><tbody>
@foreach($topRestaurantItems as $row)<tr><td>{{ $row->item_name }}</td><td>{{ $row->quantity_sold }}</td><td>₹{{ number_format((float)$row->sales,2) }}</td></tr>@endforeach
</tbody></table></div>
@endif
</section>

<p class="muted">Booked room-night occupancy is reservation-based for confirmed, in-house and checked-out stays. Cancelled reservations are excluded.</p>
@endsection
