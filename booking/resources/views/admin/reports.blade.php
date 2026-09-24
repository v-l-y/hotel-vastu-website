@extends('admin.layout')
@section('title','Reports')
@section('content')
<h1>Reports</h1>
<section class="panel"><form class="grid" method="get"><label>From<input type="date" name="from" value="{{ $from->toDateString() }}"></label><label>To<input type="date" name="to" value="{{ $to->toDateString() }}"></label><div><button>Apply</button></div></form></section>

<div class="cards">
<div class="card"><strong>{{ number_format($occupancyPercent,2) }}%</strong>Booked room-night occupancy</div>
<div class="card"><strong>₹{{ number_format($adr,2) }}</strong>ADR (pre-tax)</div>
<div class="card"><strong>₹{{ number_format($roomRevenue,2) }}</strong>Room revenue (pre-tax)</div>
<div class="card"><strong>₹{{ number_format($restaurantSales,2) }}</strong>Served restaurant sales</div>
<div class="card"><strong>₹{{ number_format($paymentsTotal,2) }}</strong>Successful payments</div>
<div class="card"><strong>₹{{ number_format($refundsTotal,2) }}</strong>Refunds</div>
<div class="card"><strong>₹{{ number_format($taxTotal,2) }}</strong>Invoice tax</div>
<div class="card"><strong>{{ $bookingsCreated }}</strong>Bookings created</div>
</div>

<section class="panel"><h2>Payment method split</h2><table><thead><tr><th>Method</th><th>Total</th></tr></thead><tbody>@foreach($paymentsByMethod as $row)<tr><td>{{ str_replace('_',' ',$row->method) }}</td><td>₹{{ number_format((float)$row->total,2) }}</td></tr>@endforeach</tbody></table></section>

<section class="panel"><h2>Restaurant item sales</h2><table><thead><tr><th>Item</th><th>Quantity</th><th>Pre-tax sales</th></tr></thead><tbody>@foreach($topRestaurantItems as $row)<tr><td>{{ $row->item_name }}</td><td>{{ $row->quantity_sold }}</td><td>₹{{ number_format((float)$row->sales,2) }}</td></tr>@endforeach</tbody></table></section>

<p class="muted">Booked room-night occupancy is reservation-based for confirmed/in-house/checked-out stays in the selected date range. Cancelled reservations are excluded.</p>
@endsection
