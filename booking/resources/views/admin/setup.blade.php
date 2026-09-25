@extends('admin.layout')
@section('title','Setup')

@push('styles')
<style>
.setup-workspace{display:grid;gap:18px}
.setup-overview{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
.setup-metric{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 15px;min-width:0}
.setup-metric span{display:block;color:var(--muted);font-size:.76rem;font-weight:900;letter-spacing:.055em;text-transform:uppercase}
.setup-metric strong{display:block;font-size:1.55rem;line-height:1.1;margin-top:7px}
.setup-nav{position:sticky;top:86px;z-index:20;display:flex;gap:8px;overflow:auto;padding:8px;margin:0;border:1px solid var(--line);border-radius:14px;background:rgba(245,242,238,.96);backdrop-filter:blur(12px);scrollbar-width:thin}
.setup-nav a{display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:0 13px;border:1px solid transparent;border-radius:9px;color:#4b4038;text-decoration:none;font-weight:850;white-space:nowrap}
.setup-nav a:hover,.setup-nav a:focus-visible{background:#fff;border-color:#d8cec5;outline:none}
.setup-nav .ui-icon-box{width:28px;height:28px;flex-basis:28px;border-radius:8px}
.setup-section{scroll-margin-top:150px;padding:0;overflow:hidden}
.setup-section>.section-head{padding:18px 18px 15px;margin:0;border-bottom:1px solid var(--line-soft);background:#fff}
.setup-section-body{padding:18px}
.setup-section-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:16px;align-items:start}
.setup-section-grid.equal{grid-template-columns:repeat(2,minmax(0,1fr))}
.setup-subsection{min-width:0}
.setup-subsection+.setup-subsection{margin-top:20px}
.setup-subhead{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 12px}
.setup-subhead h3{margin:0 0 4px;font-size:1.02rem}
.setup-subhead p{margin:0}
.setup-master-card{border:1px solid var(--line-soft);border-radius:14px;background:#fcfbfa;padding:14px}
.setup-master-card+.setup-master-card{margin-top:10px}
.setup-room-type-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.setup-room-type-card{border:1px solid var(--line-soft);border-radius:14px;padding:14px;background:#fff}
.setup-room-type-card .room-type-heading{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:12px}
.setup-room-type-card .room-type-name{font-weight:900;font-size:1rem}
.setup-room-type-card .room-type-code{display:block;color:var(--muted);font-size:.8rem;margin-top:2px}
.setup-room-type-card .grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.setup-room-type-card label{font-size:.84rem}
.setup-room-type-card .room-type-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;grid-column:1/-1;margin-top:2px}
.setup-check{display:inline-flex;align-items:center;gap:7px;font-weight:800}
.setup-check input{min-height:auto;width:17px;height:17px}
.setup-create{border:1px solid var(--line-soft);border-radius:14px;background:#fff;overflow:hidden}
.setup-create[open]{box-shadow:0 10px 28px rgba(35,30,26,.06)}
.setup-create>summary{list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 15px;cursor:pointer;font-weight:900}
.setup-create>summary::-webkit-details-marker{display:none}
.setup-create>summary:after{content:"+";display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:#eee8e2;color:#3b2e27;font-size:1.2rem;line-height:1}
.setup-create[open]>summary:after{content:"–"}
.setup-create .setup-form{border-top:1px solid var(--line-soft);padding:14px 15px;background:#fcfbfa}
.setup-create .setup-form .grid{gap:11px}
.setup-form-note{color:var(--muted);font-size:.86rem;font-weight:500;margin:-2px 0 12px}
.setup-actions{display:flex;gap:8px;align-items:center;justify-content:flex-end;flex-wrap:wrap}
.setup-table-card{border:1px solid var(--line-soft);border-radius:14px;background:#fff;padding:14px}
.setup-table-card .table-wrap{border-radius:10px}
.setup-table-card th,.setup-table-card td{padding:10px 11px}
.setup-table-card .pagination-bar{margin-bottom:0}
.setup-empty-compact{padding:28px 18px}
.setup-inline-forms{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.setup-inline-forms .setup-create{height:max-content}
.setup-chip{display:inline-flex;align-items:center;gap:5px;border:1px solid #ded6ce;border-radius:999px;padding:4px 8px;background:#faf8f6;color:#5f554d;font-size:.78rem;font-weight:800}
.setup-price{font-weight:900;white-space:nowrap}
.setup-period{white-space:nowrap}
.setup-divider{height:1px;background:var(--line-soft);margin:18px 0}
.setup-table-title{display:flex;align-items:center;gap:9px}
.setup-table-title .ui-icon-box{width:30px;height:30px;flex-basis:30px;border-radius:9px}
.setup-restaurant-grid{display:grid;grid-template-columns:minmax(0,.75fr) minmax(0,1.25fr);gap:16px;align-items:start}
.setup-section button{min-height:40px}
.setup-section input,.setup-section select,.setup-section textarea{min-height:40px}
@media(max-width:1200px){
.setup-overview{grid-template-columns:repeat(3,minmax(0,1fr))}
.setup-room-type-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
.setup-inline-forms{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:900px){
.setup-nav{top:72px}
.setup-section-grid,.setup-section-grid.equal,.setup-restaurant-grid{grid-template-columns:1fr}
.setup-room-type-grid{grid-template-columns:1fr}
.setup-inline-forms{grid-template-columns:1fr}
}
@media(max-width:640px){
.setup-overview{grid-template-columns:repeat(2,minmax(0,1fr))}
.setup-section>.section-head,.setup-section-body{padding:14px}
.setup-room-type-card .grid{grid-template-columns:1fr}
.setup-room-type-card .room-type-actions{grid-column:auto;align-items:stretch;flex-direction:column}
.setup-room-type-card .room-type-actions button{width:100%}
.setup-nav{top:64px;margin-inline:-2px}
.setup-period{white-space:normal}
}
</style>
@endpush

@section('content')
<div class="setup-workspace">
<section class="page-hero">
<div class="section-title">
@include('admin.partials.icon',['name'=>'setup'])
<div>
<h1>Hotel setup</h1>
<p>Manage hotel inventory, pricing, promotions, taxes and restaurant masters from one controlled workspace. Operational history remains unchanged.</p>
</div>
</div>
</section>

<section class="setup-overview" aria-label="Setup summary">
<div class="setup-metric"><span>Room types</span><strong>{{ $roomTypes->count() }}</strong></div>
<div class="setup-metric"><span>Physical rooms</span><strong>{{ $roomList->total() }}</strong></div>
<div class="setup-metric"><span>Rate plans</span><strong>{{ $ratePlans->count() }}</strong></div>
<div class="setup-metric"><span>Promo codes</span><strong>{{ $promotionCodes->total() }}</strong></div>
<div class="setup-metric"><span>Menu items</span><strong>{{ $restaurantMenuItems->total() }}</strong></div>
</section>

<nav class="setup-nav" aria-label="Setup sections">
<a href="#rooms">@include('admin.partials.icon',['name'=>'front-desk'])<span>Rooms</span></a>
<a href="#rates">@include('admin.partials.icon',['name'=>'calendar'])<span>Rates</span></a>
<a href="#promotions">@include('admin.partials.icon',['name'=>'filter'])<span>Promotions</span></a>
<a href="#taxes">@include('admin.partials.icon',['name'=>'payments'])<span>Taxes</span></a>
<a href="#restaurant">@include('admin.partials.icon',['name'=>'restaurant'])<span>Restaurant</span></a>
</nav>

<section class="panel setup-section" id="rooms">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'front-desk'])
<div><h2>Rooms & inventory</h2><p class="muted">Maintain room types, physical inventory and temporary maintenance blocks.</p></div>
</div>
<span class="status-badge">{{ $roomList->total() }} room(s)</span>
</div>

<div class="setup-section-body">
<div class="setup-subsection">
<div class="setup-subhead">
<div><h3>Room types</h3><p class="muted">Capacity and base pricing defaults for each sellable room category.</p></div>
</div>

<div class="setup-room-type-grid">
@foreach($roomTypes as $type)
<form class="setup-room-type-card" method="post" action="{{ route('admin.setup.room-types.update',$type) }}">
@csrf
<div class="room-type-heading">
<div>
<span class="room-type-name">{{ $type->name }}</span>
<span class="room-type-code">{{ $type->code }}</span>
</div>
<span class="status-badge {{ $type->is_active ? 'good' : 'warn' }}">{{ $type->is_active ? 'Active' : 'Inactive' }}</span>
</div>
<div class="grid">
<label>Base rate
<input type="number" step="0.01" min="0" name="base_rate" value="{{ $type->base_rate }}">
</label>
<label>Max adults
<input type="number" min="1" name="max_adults" value="{{ $type->max_adults }}">
</label>
<label>Max children
<input type="number" min="0" name="max_children" value="{{ $type->max_children }}">
</label>
<div class="room-type-actions">
<label class="setup-check"><input type="checkbox" name="is_active" value="1" @checked($type->is_active)> Active</label>
<button type="submit">Save changes</button>
</div>
</div>
</form>
@endforeach
</div>
</div>

<div class="setup-divider"></div>

<div class="setup-section-grid">
<div class="setup-subsection" id="rooms-inventory">
<div class="setup-subhead">
<div class="setup-table-title">
@include('admin.partials.icon',['name'=>'table'])
<div><h3>Physical room inventory</h3><p class="muted">Current sellable-room status and housekeeping readiness.</p></div>
</div>
<span class="status-badge">{{ $roomList->total() }} room(s)</span>
</div>

@if($roomList->count() === 0)
<div class="empty-state setup-empty-compact">No physical rooms configured.</div>
@else
<div class="setup-table-card">
<div class="table-wrap">
<table>
<thead><tr><th>Room</th><th>Type</th><th>Floor</th><th>Room status</th><th>Housekeeping</th></tr></thead>
<tbody>
@foreach($roomList as $room)
<tr>
<td><span class="table-primary">{{ $room->number }}</span></td>
<td>{{ $room->roomType?->name ?? '—' }}</td>
<td>{{ $room->floor ?: '—' }}</td>
<td><span class="status-badge {{ $room->status === 'active' ? 'good' : 'warn' }}">{{ ucfirst(str_replace('_',' ',$room->status)) }}</span></td>
<td><span class="status-badge">{{ ucfirst(str_replace('_',' ',(string)$room->housekeeping_status)) }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$roomList,'label'=>'Room inventory pagination','fragment'=>'rooms-inventory'])
</div>
@endif
</div>

<div class="setup-subsection">
<details class="setup-create" @if(old('room_type_id') || old('number') || old('floor')) open @endif>
<summary>Add physical room</summary>
<div class="setup-form">
<p class="setup-form-note">Create a saleable physical room under an existing room type.</p>
<form method="post" action="{{ route('admin.setup.rooms.store') }}">
@csrf
<div class="grid">
<label>Room type
<select name="room_type_id" required>
@foreach($roomTypes as $type)
<option value="{{ $type->id }}" @selected((string)old('room_type_id')===(string)$type->id)>{{ $type->name }}</option>
@endforeach
</select>
</label>
<label>Room number
<input name="number" value="{{ old('number') }}" required>
</label>
<label>Floor
<input name="floor" value="{{ old('floor') }}">
</label>
<div class="setup-actions"><button type="submit">Add room</button></div>
</div>
</form>
</div>
</details>

<details class="setup-create" style="margin-top:12px" @if(old('room_id')) open @endif>
<summary>Block room for maintenance</summary>
<div class="setup-form">
<p class="setup-form-note">Temporarily remove a room from sale for a checkout-style date range.</p>
<form method="post" action="{{ route('admin.setup.room-blocks.store') }}">
@csrf
<div class="grid">
<label class="full">Room
<select name="room_id" required>
@foreach($rooms as $room)
<option value="{{ $room->id }}" @selected((string)old('room_id')===(string)$room->id)>{{ $room->number }} · {{ $room->roomType->name }}</option>
@endforeach
</select>
</label>
<label>From
<input type="date" name="starts_on" value="{{ old('starts_on') }}" required>
</label>
<label>Until
<input type="date" name="ends_on" value="{{ old('ends_on') }}" required>
</label>
<label class="full">Reason
<input name="reason" value="{{ old('reason') }}">
</label>
<div class="full setup-actions"><button type="submit">Block room</button></div>
</div>
</form>
</div>
</details>
</div>
</div>

<div class="setup-divider"></div>

<div class="setup-subsection">
<div class="setup-subhead">
<div><h3>Maintenance blocks</h3><p class="muted">Active room closures that currently remove inventory from sale.</p></div>
<span class="status-badge">{{ $roomBlocks->count() }} active</span>
</div>
@if($roomBlocks->isEmpty())
<div class="empty-state setup-empty-compact">No active maintenance blocks.</div>
@else
<div class="setup-table-card">
<div class="table-wrap">
<table>
<thead><tr><th>Room</th><th>From</th><th>Until</th><th>Reason</th><th>Action</th></tr></thead>
<tbody>
@foreach($roomBlocks as $block)
<tr>
<td><span class="table-primary">{{ $block->room->number }}</span></td>
<td>{{ $block->starts_on->format('d M Y') }}</td>
<td>{{ $block->ends_on->format('d M Y') }}</td>
<td>{{ $block->reason ?: '—' }}</td>
<td>
<form method="post" action="{{ route('admin.setup.room-blocks.close',$block) }}">
@csrf
<button type="submit">Close block</button>
</form>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
</div>
@endif
</div>
</div>
</section>

<section class="panel setup-section" id="rates">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'calendar'])
<div><h2>Rates</h2><p class="muted">Manage sellable rate plans and effective-date room pricing.</p></div>
</div>
<span class="status-badge">{{ $roomRates->total() }} dated rate(s)</span>
</div>

<div class="setup-section-body">
<div class="setup-section-grid">
<div class="setup-subsection">
<div class="setup-subhead">
<div><h3>Current rate plans</h3><p class="muted">Plans available to public and desk bookings.</p></div>
<span class="status-badge">{{ $ratePlans->count() }} plan(s)</span>
</div>

@if($ratePlans->isEmpty())
<div class="empty-state setup-empty-compact">No rate plans configured.</div>
@else
<div class="setup-table-card">
<div class="table-wrap">
<table>
<thead><tr><th>Plan</th><th>Code</th><th>Breakfast</th><th>Status</th></tr></thead>
<tbody>
@foreach($ratePlans as $plan)
<tr>
<td><span class="table-primary">{{ $plan->name }}</span></td>
<td><code>{{ $plan->code }}</code></td>
<td>{{ $plan->includes_breakfast ? 'Included' : 'Not included' }}</td>
<td><span class="status-badge {{ $plan->is_active ? 'good' : 'warn' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
</div>
@endif
</div>

<div class="setup-subsection">
<details class="setup-create" @if(old('includes_breakfast') !== null) open @endif>
<summary>Add rate plan</summary>
<div class="setup-form">
<form method="post" action="{{ route('admin.setup.rate-plans.store') }}">
@csrf
<div class="grid">
<label class="full">Name
<input name="name" value="{{ old('name') }}" required>
</label>
<label class="full">Code
<input name="code" value="{{ old('code') }}">
</label>
<label class="full setup-check"><input type="checkbox" name="includes_breakfast" value="1" @checked(old('includes_breakfast'))> Breakfast included</label>
<div class="full setup-actions"><button type="submit">Add rate plan</button></div>
</div>
</form>
</div>
</details>

<details class="setup-create" style="margin-top:12px" @if(old('rate_plan_id') || old('nightly_rate')) open @endif>
<summary>Add dated rate</summary>
<div class="setup-form">
<form method="post" action="{{ route('admin.setup.room-rates.store') }}">
@csrf
<div class="grid">
<label>Room type
<select name="room_type_id" required>
@foreach($roomTypes as $type)
<option value="{{ $type->id }}" @selected((string)old('room_type_id')===(string)$type->id)>{{ $type->name }}</option>
@endforeach
</select>
</label>
<label>Rate plan
<select name="rate_plan_id" required>
@foreach($ratePlans as $plan)
<option value="{{ $plan->id }}" @selected((string)old('rate_plan_id')===(string)$plan->id)>{{ $plan->name }}</option>
@endforeach
</select>
</label>
<label>From
<input type="date" name="starts_on" value="{{ old('starts_on') }}" required>
</label>
<label>To
<input type="date" name="ends_on" value="{{ old('ends_on') }}" required>
</label>
<label>Nightly rate
<input type="number" step="0.01" min="0" name="nightly_rate" value="{{ old('nightly_rate') }}" required>
</label>
<label>Min stay
<input type="number" min="1" name="min_stay" value="{{ old('min_stay',1) }}" required>
</label>
<label>Max stay
<input type="number" min="1" name="max_stay" value="{{ old('max_stay') }}">
</label>
<div class="setup-actions"><button type="submit">Add dated rate</button></div>
</div>
</form>
</div>
</details>
</div>
</div>

<div class="setup-divider"></div>

<div class="setup-subsection" id="dated-rates">
<div class="setup-subhead">
<div class="setup-table-title">
@include('admin.partials.icon',['name'=>'table'])
<div><h3>Dated rates</h3><p class="muted">Newest effective pricing periods first.</p></div>
</div>
<span class="status-badge">{{ $roomRates->total() }} rate(s)</span>
</div>

@if($roomRates->count() === 0)
<div class="empty-state setup-empty-compact">No dated room rates configured.</div>
@else
<div class="setup-table-card">
<div class="table-wrap">
<table>
<thead><tr><th>Room type</th><th>Rate plan</th><th>Period</th><th>Nightly rate</th><th>Stay limits</th></tr></thead>
<tbody>
@foreach($roomRates as $rate)
<tr>
<td><span class="table-primary">{{ $rate->roomType?->name ?? '—' }}</span></td>
<td>{{ $rate->ratePlan?->name ?? '—' }}</td>
<td class="setup-period">{{ $rate->starts_on->format('d M Y') }} → {{ $rate->ends_on->format('d M Y') }}</td>
<td class="setup-price">₹{{ number_format((float)$rate->nightly_rate,2) }}</td>
<td>
@if($rate->max_stay)
{{ $rate->min_stay }}–{{ $rate->max_stay }} nights
@else
{{ $rate->min_stay }}+ nights
@endif
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$roomRates,'label'=>'Dated rates pagination','fragment'=>'dated-rates'])
</div>
@endif
</div>
</div>
</section>

<section class="panel setup-section" id="promotions">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'filter'])
<div><h2>Promotions</h2><p class="muted">Controlled public and desk discounts with date, spend and usage rules.</p></div>
</div>
<span class="status-badge">{{ $promotionCodes->total() }} code(s)</span>
</div>

<div class="setup-section-body">
<div class="setup-section-grid">
<div class="setup-subsection" id="promo-list">
<div class="setup-subhead">
<div><h3>Promo code registry</h3><p class="muted">Latest promotion codes first.</p></div>
<span class="status-badge">{{ $promotionCodes->total() }} code(s)</span>
</div>

@if($promotionCodes->count() === 0)
<div class="empty-state setup-empty-compact">No promo codes configured.</div>
@else
<div class="setup-table-card">
<div class="table-wrap">
<table>
<thead><tr><th>Code</th><th>Discount</th><th>Rules</th><th>Usage</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
@foreach($promotionCodes as $promo)
<tr>
<td>
<span class="table-primary">{{ $promo->code }}</span>
<span class="table-secondary">{{ $promo->name }}</span>
</td>
<td>
@if($promo->discount_type === 'percent')
<span class="setup-price">{{ number_format((float)$promo->discount_value,2) }}%</span>
@else
<span class="setup-price">₹{{ number_format((float)$promo->discount_value,2) }}</span>
@endif
@if($promo->max_discount)
<span class="table-secondary">Cap ₹{{ number_format((float)$promo->max_discount,2) }}</span>
@endif
</td>
<td>
<span class="table-secondary">Min ₹{{ number_format((float)$promo->min_subtotal,2) }}</span>
<span class="table-secondary">{{ $promo->starts_on?->format('d M Y') ?? 'Any start' }} → {{ $promo->ends_on?->format('d M Y') ?? 'No expiry' }}</span>
</td>
<td>{{ $promo->times_used }}{{ $promo->usage_limit ? ' / '.$promo->usage_limit : '' }}</td>
<td><span class="status-badge {{ $promo->is_active ? 'good' : 'warn' }}">{{ $promo->is_active ? 'Active' : 'Inactive' }}</span></td>
<td>
<form method="post" action="{{ route('admin.setup.promotion-codes.status',$promo) }}">
@csrf
<input type="hidden" name="is_active" value="{{ $promo->is_active ? 0 : 1 }}">
<button type="submit">{{ $promo->is_active ? 'Deactivate' : 'Activate' }}</button>
</form>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$promotionCodes,'label'=>'Promo code pagination','fragment'=>'promo-list'])
</div>
@endif
</div>

<div class="setup-subsection">
<details class="setup-create" @if(old('discount_type') || old('discount_value') || old('usage_limit')) open @endif>
<summary>Create promo code</summary>
<div class="setup-form">
<form method="post" action="{{ route('admin.setup.promotion-codes.store') }}">
@csrf
<div class="grid">
<label>Code
<input name="code" maxlength="40" placeholder="WELCOME10" value="{{ old('code') }}" required>
</label>
<label>Name
<input name="name" maxlength="120" placeholder="Welcome offer" value="{{ old('name') }}" required>
</label>
<label>Discount type
<select name="discount_type">
<option value="percent" @selected(old('discount_type','percent')==='percent')>Percent</option>
<option value="fixed" @selected(old('discount_type')==='fixed')>Fixed amount</option>
</select>
</label>
<label>Value
<input type="number" step="0.01" min="0.01" name="discount_value" value="{{ old('discount_value') }}" required>
</label>
<label>Max discount
<input type="number" step="0.01" min="0.01" name="max_discount" value="{{ old('max_discount') }}" placeholder="Optional cap">
</label>
<label>Minimum room subtotal
<input type="number" step="0.01" min="0" name="min_subtotal" value="{{ old('min_subtotal',0) }}">
</label>
<label>Active from
<input type="date" name="starts_on" value="{{ old('starts_on') }}">
</label>
<label>Active until
<input type="date" name="ends_on" value="{{ old('ends_on') }}">
</label>
<label>Usage limit
<input type="number" min="1" name="usage_limit" value="{{ old('usage_limit') }}" placeholder="Optional">
</label>
<div class="setup-actions"><button type="submit">Create promo code</button></div>
</div>
</form>
</div>
</details>
</div>
</div>
</div>
</section>

<section class="panel setup-section" id="taxes">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'payments'])
<div><h2>Taxes</h2><p class="muted">Effective hotel and restaurant tax percentages used by billing.</p></div>
</div>
<span class="status-badge">{{ $taxRules->count() }} rule(s)</span>
</div>

<div class="setup-section-body">
<div class="setup-section-grid">
<div class="setup-subsection">
<div class="setup-subhead">
<div><h3>Tax rule registry</h3><p class="muted">Configured effective tax periods.</p></div>
</div>

@if($taxRules->isEmpty())
<div class="empty-state setup-empty-compact">No tax rules configured.</div>
@else
<div class="setup-table-card">
<div class="table-wrap">
<table>
<thead><tr><th>Rule</th><th>Applies to</th><th>Rate</th><th>Effective period</th><th>Status</th></tr></thead>
<tbody>
@foreach($taxRules as $rule)
<tr>
<td><span class="table-primary">{{ $rule->name }}</span></td>
<td><span class="setup-chip">{{ ucfirst($rule->applies_to) }}</span></td>
<td class="setup-price">{{ number_format((float)$rule->rate_percent,4) }}%</td>
<td>{{ $rule->effective_from?->format('d M Y') ?? 'Any start' }} → {{ $rule->effective_to?->format('d M Y') ?? 'No expiry' }}</td>
<td><span class="status-badge {{ $rule->is_active ? 'good' : 'warn' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
</div>
@endif
</div>

<div class="setup-subsection">
<details class="setup-create" @if(old('rate_percent') || old('applies_to')) open @endif>
<summary>Add tax rule</summary>
<div class="setup-form">
<form method="post" action="{{ route('admin.setup.tax-rules.store') }}">
@csrf
<div class="grid">
<label class="full">Name
<input name="name" value="{{ old('name') }}" required>
</label>
<label>Applies to
<select name="applies_to">
<option value="hotel" @selected(old('applies_to')==='hotel')>Hotel</option>
<option value="restaurant" @selected(old('applies_to')==='restaurant')>Restaurant</option>
<option value="all" @selected(old('applies_to','all')==='all')>All</option>
</select>
</label>
<label>Rate %
<input type="number" step="0.0001" min="0" max="100" name="rate_percent" value="{{ old('rate_percent') }}" required>
</label>
<label>Effective from
<input type="date" name="effective_from" value="{{ old('effective_from') }}">
</label>
<label>Effective to
<input type="date" name="effective_to" value="{{ old('effective_to') }}">
</label>
<div class="full setup-actions"><button type="submit">Add tax rule</button></div>
</div>
</form>
</div>
</details>
</div>
</div>
</div>
</section>

<section class="panel setup-section" id="restaurant">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'restaurant'])
<div><h2>Restaurant masters</h2><p class="muted">Manage menu categories, items and physical dining tables.</p></div>
</div>
<div class="actions">
<span class="status-badge">{{ $restaurantMenuItems->total() }} item(s)</span>
<span class="status-badge">{{ $restaurantTables->total() }} table(s)</span>
</div>
</div>

<div class="setup-section-body">
<div class="setup-inline-forms">
<details class="setup-create">
<summary>Add category</summary>
<div class="setup-form">
<form method="post" action="{{ route('admin.setup.restaurant-categories.store') }}">
@csrf
<label>Category name
<input name="name" value="{{ old('name') }}" required>
</label>
<div class="setup-actions" style="margin-top:12px"><button type="submit">Add category</button></div>
</form>
</div>
</details>

<details class="setup-create" @if(old('restaurant_category_id') || old('price')) open @endif>
<summary>Add menu item</summary>
<div class="setup-form">
<form method="post" action="{{ route('admin.setup.restaurant-menu-items.store') }}">
@csrf
<div class="grid">
<label>Category
<select name="restaurant_category_id" required>
@foreach($restaurantCategories as $category)
<option value="{{ $category->id }}" @selected((string)old('restaurant_category_id')===(string)$category->id)>{{ $category->name }}</option>
@endforeach
</select>
</label>
<label>Item name
<input name="name" value="{{ old('name') }}" required>
</label>
<label>Price
<input type="number" step="0.01" min="0" name="price" value="{{ old('price') }}" required>
</label>
<label class="setup-check"><input type="checkbox" name="is_vegetarian" value="1" @checked(old('is_vegetarian'))> Vegetarian</label>
<div class="full setup-actions"><button type="submit">Add menu item</button></div>
</div>
</form>
</div>
</details>

<details class="setup-create" @if(old('capacity')) open @endif>
<summary>Add dining table</summary>
<div class="setup-form">
<form method="post" action="{{ route('admin.setup.restaurant-tables.store') }}">
@csrf
<div class="grid">
<label>Code
<input name="code" value="{{ old('code') }}" required>
</label>
<label>Name
<input name="name" value="{{ old('name') }}" required>
</label>
<label>Capacity
<input type="number" min="1" name="capacity" value="{{ old('capacity') }}">
</label>
<div class="setup-actions"><button type="submit">Add table</button></div>
</div>
</form>
</div>
</details>
</div>

<div class="setup-divider"></div>

<div class="setup-restaurant-grid">
<div class="setup-subsection">
<div class="setup-subhead">
<div><h3>Categories</h3><p class="muted">Display order and master availability.</p></div>
<span class="status-badge">{{ $restaurantCategories->count() }}</span>
</div>
@if($restaurantCategories->isEmpty())
<div class="empty-state setup-empty-compact">No restaurant categories.</div>
@else
<div class="setup-table-card">
<div class="table-wrap">
<table>
<thead><tr><th>Category</th><th>Sort</th><th>Status</th></tr></thead>
<tbody>
@foreach($restaurantCategories as $category)
<tr>
<td><span class="table-primary">{{ $category->name }}</span></td>
<td>{{ $category->sort_order }}</td>
<td><span class="status-badge {{ $category->is_active ? 'good' : 'warn' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
</div>
@endif
</div>

<div class="setup-subsection" id="restaurant-tables">
<div class="setup-subhead">
<div class="setup-table-title">
@include('admin.partials.icon',['name'=>'restaurant-table'])
<div><h3>Dining tables</h3><p class="muted">Physical seating inventory and current operational state.</p></div>
</div>
<span class="status-badge">{{ $restaurantTables->total() }} table(s)</span>
</div>
@if($restaurantTables->count() === 0)
<div class="empty-state setup-empty-compact">No dining tables configured.</div>
@else
<div class="setup-table-card">
<div class="table-wrap">
<table>
<thead><tr><th>Table</th><th>Capacity</th><th>Operational status</th><th>Master status</th></tr></thead>
<tbody>
@foreach($restaurantTables as $table)
<tr>
<td>
<span class="table-primary">{{ $table->name }}</span>
<span class="table-secondary">{{ $table->code }}</span>
</td>
<td>{{ $table->capacity ?? '—' }}{{ $table->capacity ? ' seats' : '' }}</td>
<td><span class="status-badge">{{ ucfirst(str_replace('_',' ',(string)$table->status)) }}</span></td>
<td><span class="status-badge {{ $table->is_active ? 'good' : 'warn' }}">{{ $table->is_active ? 'Active' : 'Inactive' }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$restaurantTables,'label'=>'Restaurant tables pagination','fragment'=>'restaurant-tables'])
</div>
@endif
</div>
</div>

<div class="setup-divider"></div>

<div class="setup-subsection" id="restaurant-menu">
<div class="setup-subhead">
<div class="setup-table-title">
@include('admin.partials.icon',['name'=>'table'])
<div><h3>Menu registry</h3><p class="muted">Menu items grouped by configured category.</p></div>
</div>
<span class="status-badge">{{ $restaurantMenuItems->total() }} item(s)</span>
</div>
@if($restaurantMenuItems->count() === 0)
<div class="empty-state setup-empty-compact">No restaurant menu items configured.</div>
@else
<div class="setup-table-card">
<div class="table-wrap">
<table>
<thead><tr><th>Item</th><th>Category</th><th>Type</th><th>Price</th><th>Status</th></tr></thead>
<tbody>
@foreach($restaurantMenuItems as $item)
<tr>
<td><span class="table-primary">{{ $item->name }}</span></td>
<td>{{ $item->category?->name ?? '—' }}</td>
<td><span class="setup-chip">{{ $item->is_vegetarian ? 'Vegetarian' : 'Non-vegetarian' }}</span></td>
<td class="setup-price">₹{{ number_format((float)$item->price,2) }}</td>
<td><span class="status-badge {{ $item->is_active ? 'good' : 'warn' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$restaurantMenuItems,'label'=>'Restaurant menu pagination','fragment'=>'restaurant-menu'])
</div>
@endif
</div>
</div>
</section>
</div>
@endsection
