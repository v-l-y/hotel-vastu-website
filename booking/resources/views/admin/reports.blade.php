@extends('admin.layout')
@section('title','Reports')
@section('content')
<h1>Reports</h1>
<section class="panel"><form class="grid" method="get"><label>From<input type="date" name="from" value="{{ $from->toDateString() }}"></label><label>To<input type="date" name="to" value="{{ $to->toDateString() }}"></label><div><button>Apply</button></div></form></section>
<div class="cards"><div class="card"><strong>₹{{ number_format($paymentsTotal,2) }}</strong>Successful payments</div><div class="card"><strong>{{ $bookingsCreated }}</strong>Bookings created</div><div class="card"><strong>{{ $cancelledBookings }}</strong>Cancelled bookings</div><div class="card"><strong>₹{{ number_format($restaurantSales,2) }}</strong>Served restaurant sales</div></div>
@endsection
