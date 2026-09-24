@extends('admin.layout')
@section('title','Dashboard')
@push('styles')
<style>
.workspace-link{display:grid;align-content:start;justify-content:stretch;text-align:left;gap:6px;min-height:118px;text-decoration:none}
.workspace-link strong{font-size:1.1rem}
.workspace-link span{color:#6f675f;font-weight:600;line-height:1.45}
.workspace-link:hover{border-color:#bda996;box-shadow:0 8px 22px rgba(35,30,26,.07)}
</style>
@endpush
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
<a class="card workspace-link" href="{{ route('admin.front-desk') }}"><strong>Front Desk</strong><span>Arrivals, stays, rooms and housekeeping</span></a>
@endif
@if(in_array($admin->role,['administrator','restaurant','kitchen'],true))
<a class="card workspace-link" href="{{ route('admin.restaurant') }}"><strong>{{ $admin->role==='kitchen' ? 'Kitchen KOT' : 'Restaurant & KOT' }}</strong><span>Orders and kitchen workflow</span></a>
@endif
@if(in_array($admin->role,['administrator','front_desk','accounts','restaurant'],true))
<a class="card workspace-link" href="{{ route('admin.payments') }}"><strong>Payments</strong><span>Balances, payments and eligible refunds</span></a>
@endif
@if(in_array($admin->role,['administrator','front_desk','accounts'],true))
<a class="card workspace-link" href="{{ route('admin.reports') }}"><strong>Reports</strong><span>Operational and revenue reporting</span></a>
@endif
<a class="card workspace-link" href="{{ route('admin.security') }}"><strong>Security</strong><span>Two-factor authentication and session protection</span></a>
</div>
</section>
@endsection
