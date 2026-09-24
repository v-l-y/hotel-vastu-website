@extends('admin.layout')
@section('title','Dashboard')
@section('content')
<h1>Dashboard</h1><p class="muted">Signed in as {{ $admin->name }} · {{ str_replace('_',' ',$admin->role) }}</p>
<div class="cards"><div class="card"><strong>{{ $confirmedReservations }}</strong>Confirmed arrivals</div><div class="card"><strong>{{ $inHouse }}</strong>In-house stays</div><div class="card"><strong>{{ $openFolios }}</strong>Open folios</div><div class="card"><strong>{{ $activeRestaurantOrders }}</strong>Active restaurant orders</div></div>
@endsection
