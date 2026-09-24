@extends('admin.layout')
@section('title','Dashboard')
@section('content')
<section class="page-hero">
<div>
<h1>Dashboard</h1>
<p>Welcome back, {{ $admin->name }}. Your workspace is scoped to the {{ str_replace('_',' ',$admin->role) }} role.</p>
</div>
<span class="status-badge">{{ ucfirst(str_replace('_',' ',$admin->role)) }}</span>
</section>

<div class="metric-grid">
@foreach($cards as $card)
<div class="stat-card">
<span class="muted">{{ $card['label'] }}</span>
<strong>{{ $card['value'] }}</strong>
<small>Current operational count</small>
</div>
@endforeach
</div>

<section class="panel">
<div class="section-head">
<div><h2>Your workspace</h2><p class="muted">Open the operational areas available to your role.</p></div>
</div>
<div class="cards">
@if(in_array($admin->role,['administrator','front_desk'],true))
<a class="card button-link" href="{{ route('admin.front-desk') }}"><strong>Front Desk</strong><span>Arrivals, stays, rooms and housekeeping</span></a>
@endif
@if(in_array($admin->role,['administrator','restaurant','kitchen'],true))
<a class="card button-link" href="{{ route('admin.restaurant') }}"><strong>{{ $admin->role==='kitchen' ? 'Kitchen KOT' : 'Restaurant & KOT' }}</strong><span>Orders and kitchen workflow</span></a>
@endif
@if(in_array($admin->role,['administrator','front_desk','accounts','restaurant'],true))
<a class="card button-link" href="{{ route('admin.payments') }}"><strong>Payments</strong><span>Balances, payments and eligible refunds</span></a>
@endif
@if(in_array($admin->role,['administrator','front_desk','accounts'],true))
<a class="card button-link" href="{{ route('admin.reports') }}"><strong>Reports</strong><span>Operational and revenue reporting</span></a>
@endif
<a class="card button-link" href="{{ route('admin.security') }}"><strong>Security</strong><span>Two-factor authentication and session protection</span></a>
</div>
</section>
@endsection
