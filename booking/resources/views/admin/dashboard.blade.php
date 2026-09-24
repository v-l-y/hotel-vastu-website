@extends('admin.layout')
@section('title','Dashboard')
@section('content')
<h1>Dashboard</h1>
<p class="muted">Signed in as {{ $admin->name }} · {{ str_replace('_',' ',$admin->role) }}</p>
<div class="cards">
@foreach($cards as $card)
<div class="card"><strong>{{ $card['value'] }}</strong>{{ $card['label'] }}</div>
@endforeach
</div>
@endsection
