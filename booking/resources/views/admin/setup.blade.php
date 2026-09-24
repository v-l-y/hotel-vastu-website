@extends('admin.layout')
@section('title','Setup')
@section('content')

<section class="page-hero">
<div class="section-title">
@include('admin.partials.icon',['name'=>'setup'])
<div>
<h1>Hotel setup</h1>
<p>Configure inventory, rates, promotions, taxes and restaurant masters without changing operational history.</p>
</div>
</div>
</section>

<nav class="tabs" aria-label="Setup sections">
<a href="#rooms">Rooms</a>
<a href="#rates">Rates</a>
<a href="#promotions">Promotions</a>
<a href="#taxes">Taxes</a>
<a href="#restaurant">Restaurant</a>
</nav>

<section class="panel" id="rooms">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'front-desk'])
<div><h2>Rooms & inventory</h2><p class="muted">Room types, physical rooms and maintenance blocks.</p></div>
</div>
</div>

<h3>Room types</h3>
@foreach($roomTypes as $type)
<form class="grid" method="post" action="{{ route('admin.setup.room-types.update',$type) }}">
@csrf
<div>
<span class="table-primary">{{ $type->name }}</span>
<span class="table-secondary">{{ $type->code }}</span>
</div>
<label>Base rate<input type="number" step="0.01" min="0" name="base_rate" value="{{ $type->base_rate }}"></label>
<label>Max adults<input type="number" min="1" name="max_adults" value="{{ $type->max_adults }}"></label>
<label>Max children<input type="number" min="0" name="max_children" value="{{ $type->max_children }}"></label>
<label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_active" value="1" @checked($type->is_active)> Active</label>
<div><button type="submit">Save {{ $type->name }}</button></div>
</form>
@if(!$loop->last)
<hr style="border:0;border-top:1px solid #ebe5df;margin:18px 0">
@endif
@endforeach

<hr style="border:0;border-top:1px solid #ebe5df;margin:22px 0">

<div class="section-head">
<div><h3 style="margin:0 0 4px">Add physical room</h3><p class="muted">Create a saleable physical room under an existing room type.</p></div>
</div>
<form class="grid" method="post" action="{{ route('admin.setup.rooms.store') }}">
@csrf
<label>Room type
<select name="room_type_id" required>
@foreach($roomTypes as $type)
<option value="{{ $type->id }}" @selected((string)old('room_type_id')===(string)$type->id)>{{ $type->name }}</option>
@endforeach
</select>
</label>
<label>Room number<input name="number" value="{{ old('number') }}" required></label>
<label>Floor<input name="floor" value="{{ old('floor') }}"></label>
<div><button type="submit">Add room</button></div>
</form>

<div id="rooms-inventory" style="margin-top:20px">
<div class="section-head">
<div><h3 style="margin:0 0 4px">Physical room inventory</h3><p class="muted">Current room status and housekeeping readiness.</p></div>
<span class="status-badge">{{ $roomList->total() }} room(s)</span>
</div>
@if($roomList->count() === 0)
<div class="empty-state">No physical rooms configured.</div>
@else
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
@endif
</div>

<hr style="border:0;border-top:1px solid #ebe5df;margin:22px 0">

<div class="section-head">
<div><h3 style="margin:0 0 4px">Maintenance blocks</h3><p class="muted">Temporarily remove a room from sale for a specific date range.</p></div>
</div>
<form class="grid" method="post" action="{{ route('admin.setup.room-blocks.store') }}">
@csrf
<label>Room
<select name="room_id" required>
@foreach($rooms as $room)
<option value="{{ $room->id }}" @selected((string)old('room_id')===(string)$room->id)>{{ $room->number }} · {{ $room->roomType->name }}</option>
@endforeach
</select>
</label>
<label>From<input type="date" name="starts_on" value="{{ old('starts_on') }}" required></label>
<label>Until (checkout-style end)<input type="date" name="ends_on" value="{{ old('ends_on') }}" required></label>
<label>Reason<input name="reason" value="{{ old('reason') }}"></label>
<div><button type="submit">Block room</button></div>
</form>

@if($roomBlocks->isEmpty())
<div class="empty-state" style="margin-top:16px">No active maintenance blocks.</div>
@else
<div class="table-wrap" style="margin-top:16px">
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
@endif
</section>

<section class="panel" id="rates">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'calendar'])
<div><h2>Rate plans & dated rates</h2><p class="muted">Create sellable plans and effective-date room pricing.</p></div>
</div>
</div>

<div class="grid">
<form class="panel" method="post" action="{{ route('admin.setup.rate-plans.store') }}">
@csrf
<h3>New rate plan</h3>
<label>Name<input name="name" value="{{ old('name') }}" required></label>
<label>Code<input name="code" value="{{ old('code') }}"></label>
<label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="includes_breakfast" value="1" @checked(old('includes_breakfast'))> Breakfast included</label>
<button type="submit">Add rate plan</button>
</form>

<div class="panel">
<h3>Current rate plans</h3>
@if($ratePlans->isEmpty())
<div class="empty-state">No rate plans configured.</div>
@else
<div class="table-wrap">
<table>
<thead><tr><th>Plan</th><th>Code</th><th>Breakfast</th><th>Status</th></tr></thead>
<tbody>
@foreach($ratePlans as $plan)
<tr>
<td>{{ $plan->name }}</td>
<td><code>{{ $plan->code }}</code></td>
<td>{{ $plan->includes_breakfast ? 'Included' : 'Not included' }}</td>
<td><span class="status-badge {{ $plan->is_active ? 'good' : 'warn' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@endif
</div>
</div>

<form class="grid" method="post" action="{{ route('admin.setup.room-rates.store') }}">
@csrf
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
<label>From<input type="date" name="starts_on" value="{{ old('starts_on') }}" required></label>
<label>To<input type="date" name="ends_on" value="{{ old('ends_on') }}" required></label>
<label>Nightly rate<input type="number" step="0.01" min="0" name="nightly_rate" value="{{ old('nightly_rate') }}" required></label>
<label>Min stay<input type="number" min="1" name="min_stay" value="{{ old('min_stay',1) }}" required></label>
<label>Max stay<input type="number" min="1" name="max_stay" value="{{ old('max_stay') }}"></label>
<div><button type="submit">Add dated rate</button></div>
</form>

<div id="dated-rates" style="margin-top:20px">
<div class="section-head">
<div><h3 style="margin:0 0 4px">Dated rates</h3><p class="muted">Newest effective periods first.</p></div>
<span class="status-badge">{{ $roomRates->total() }} rate(s)</span>
</div>
@if($roomRates->count() === 0)
<div class="empty-state">No dated room rates configured.</div>
@else
<div class="table-wrap">
<table>
<thead><tr><th>Room type</th><th>Rate plan</th><th>Period</th><th>Nightly rate</th><th>Stay limits</th></tr></thead>
<tbody>
@foreach($roomRates as $rate)
<tr>
<td>{{ $rate->roomType?->name ?? '—' }}</td>
<td>{{ $rate->ratePlan?->name ?? '—' }}</td>
<td>{{ $rate->starts_on->format('d M Y') }} → {{ $rate->ends_on->format('d M Y') }}</td>
<td>₹{{ number_format((float)$rate->nightly_rate,2) }}</td>
<td>{{ $rate->min_stay }} min@if($rate->max_stay) · {{ $rate->max_stay }} max@endif</td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$roomRates,'label'=>'Dated rates pagination','fragment'=>'dated-rates'])
@endif
</div>
</section>

<section class="panel" id="promotions">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'filter'])
<div><h2>Promo codes</h2><p class="muted">Controlled public/desk discounts with dates, minimum spend and usage limits.</p></div>
</div>
</div>

<form class="grid" method="post" action="{{ route('admin.setup.promotion-codes.store') }}">
@csrf
<label>Code<input name="code" maxlength="40" placeholder="WELCOME10" value="{{ old('code') }}" required></label>
<label>Name<input name="name" maxlength="120" placeholder="Welcome offer" value="{{ old('name') }}" required></label>
<label>Discount type
<select name="discount_type">
<option value="percent" @selected(old('discount_type','percent')==='percent')>Percent</option>
<option value="fixed" @selected(old('discount_type')==='fixed')>Fixed amount</option>
</select>
</label>
<label>Value<input type="number" step="0.01" min="0.01" name="discount_value" value="{{ old('discount_value') }}" required></label>
<label>Max discount<input type="number" step="0.01" min="0.01" name="max_discount" value="{{ old('max_discount') }}" placeholder="Optional cap"></label>
<label>Minimum room subtotal<input type="number" step="0.01" min="0" name="min_subtotal" value="{{ old('min_subtotal',0) }}"></label>
<label>Active from<input type="date" name="starts_on" value="{{ old('starts_on') }}"></label>
<label>Active until<input type="date" name="ends_on" value="{{ old('ends_on') }}"></label>
<label>Usage limit<input type="number" min="1" name="usage_limit" value="{{ old('usage_limit') }}" placeholder="Optional"></label>
<div><button type="submit">Create promo code</button></div>
</form>

<div id="promo-list" style="margin-top:20px">
<div class="section-head">
<div><h3 style="margin:0 0 4px">Promo code registry</h3><p class="muted">Latest codes first.</p></div>
<span class="status-badge">{{ $promotionCodes->total() }} code(s)</span>
</div>

@if($promotionCodes->count() === 0)
<div class="empty-state">No promo codes configured.</div>
@else
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
{{ number_format((float)$promo->discount_value,2) }}%
@else
₹{{ number_format((float)$promo->discount_value,2) }}
@endif
@if($promo->max_discount)
<span class="table-secondary">Max ₹{{ number_format((float)$promo->max_discount,2) }}</span>
@endif
</td>
<td>
<span class="table-secondary">Min subtotal ₹{{ number_format((float)$promo->min_subtotal,2) }}</span>
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
@endif
</div>
</section>

<section class="panel" id="taxes">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'payments'])
<div><h2>Tax rules</h2><p class="muted">Define effective hotel and restaurant tax percentages.</p></div>
</div>
</div>

<form class="grid" method="post" action="{{ route('admin.setup.tax-rules.store') }}">
@csrf
<label>Name<input name="name" value="{{ old('name') }}" required></label>
<label>Applies to
<select name="applies_to">
<option value="hotel" @selected(old('applies_to')==='hotel')>Hotel</option>
<option value="restaurant" @selected(old('applies_to')==='restaurant')>Restaurant</option>
<option value="all" @selected(old('applies_to','all')==='all')>All</option>
</select>
</label>
<label>Rate %<input type="number" step="0.0001" min="0" max="100" name="rate_percent" value="{{ old('rate_percent') }}" required></label>
<label>Effective from<input type="date" name="effective_from" value="{{ old('effective_from') }}"></label>
<label>Effective to<input type="date" name="effective_to" value="{{ old('effective_to') }}"></label>
<div><button type="submit">Add tax rule</button></div>
</form>

@if($taxRules->isEmpty())
<div class="empty-state" style="margin-top:18px">No tax rules configured.</div>
@else
<div class="table-wrap" style="margin-top:18px">
<table>
<thead><tr><th>Rule</th><th>Applies to</th><th>Rate</th><th>Effective period</th><th>Status</th></tr></thead>
<tbody>
@foreach($taxRules as $rule)
<tr>
<td>{{ $rule->name }}</td>
<td>{{ ucfirst($rule->applies_to) }}</td>
<td>{{ number_format((float)$rule->rate_percent,4) }}%</td>
<td>{{ $rule->effective_from?->format('d M Y') ?? 'Any start' }} → {{ $rule->effective_to?->format('d M Y') ?? 'No expiry' }}</td>
<td><span class="status-badge {{ $rule->is_active ? 'good' : 'warn' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@endif
</section>

<section class="panel" id="restaurant">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'restaurant'])
<div><h2>Restaurant setup</h2><p class="muted">Manage categories, menu items and physical dining tables.</p></div>
</div>
</div>

<div class="grid">
<form class="panel" method="post" action="{{ route('admin.setup.restaurant-categories.store') }}">
@csrf
<h3>New category</h3>
<label>Category name<input name="name" value="{{ old('name') }}" required></label>
<button type="submit">Add category</button>
</form>

<form class="panel" method="post" action="{{ route('admin.setup.restaurant-menu-items.store') }}">
@csrf
<h3>New menu item</h3>
<label>Category
<select name="restaurant_category_id" required>
@foreach($restaurantCategories as $category)
<option value="{{ $category->id }}" @selected((string)old('restaurant_category_id')===(string)$category->id)>{{ $category->name }}</option>
@endforeach
</select>
</label>
<label>Item name<input name="name" value="{{ old('name') }}" required></label>
<label>Price<input type="number" step="0.01" min="0" name="price" value="{{ old('price') }}" required></label>
<label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_vegetarian" value="1" @checked(old('is_vegetarian'))> Vegetarian</label>
<button type="submit">Add menu item</button>
</form>

<form class="panel" method="post" action="{{ route('admin.setup.restaurant-tables.store') }}">
@csrf
<h3>New dining table</h3>
<label>Code<input name="code" value="{{ old('code') }}" required></label>
<label>Name<input name="name" value="{{ old('name') }}" required></label>
<label>Capacity<input type="number" min="1" name="capacity" value="{{ old('capacity') }}"></label>
<button type="submit">Add table</button>
</form>
</div>

<div class="grid">
<div>
<h3>Categories</h3>
@if($restaurantCategories->isEmpty())
<div class="empty-state">No restaurant categories.</div>
@else
<div class="table-wrap">
<table>
<thead><tr><th>Category</th><th>Sort</th><th>Status</th></tr></thead>
<tbody>
@foreach($restaurantCategories as $category)
<tr>
<td>{{ $category->name }}</td>
<td>{{ $category->sort_order }}</td>
<td><span class="status-badge {{ $category->is_active ? 'good' : 'warn' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@endif
</div>

<div id="restaurant-tables">
<div class="section-head"><div><h3 style="margin:0">Dining tables</h3></div><span class="status-badge">{{ $restaurantTables->total() }} table(s)</span></div>
@if($restaurantTables->count() === 0)
<div class="empty-state">No dining tables configured.</div>
@else
<div class="table-wrap">
<table>
<thead><tr><th>Code</th><th>Name</th><th>Capacity</th><th>Operational status</th><th>Master status</th></tr></thead>
<tbody>
@foreach($restaurantTables as $table)
<tr>
<td><code>{{ $table->code }}</code></td>
<td>{{ $table->name }}</td>
<td>{{ $table->capacity ?? '—' }}</td>
<td><span class="status-badge">{{ ucfirst(str_replace('_',' ',(string)$table->status)) }}</span></td>
<td><span class="status-badge {{ $table->is_active ? 'good' : 'warn' }}">{{ $table->is_active ? 'Active' : 'Inactive' }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$restaurantTables,'label'=>'Restaurant tables pagination','fragment'=>'restaurant-tables'])
@endif
</div>
</div>

<div id="restaurant-menu" style="margin-top:20px">
<div class="section-head">
<div><h3 style="margin:0 0 4px">Menu registry</h3><p class="muted">Menu items grouped by their configured category.</p></div>
<span class="status-badge">{{ $restaurantMenuItems->total() }} item(s)</span>
</div>
@if($restaurantMenuItems->count() === 0)
<div class="empty-state">No restaurant menu items configured.</div>
@else
<div class="table-wrap">
<table>
<thead><tr><th>Item</th><th>Category</th><th>Type</th><th>Price</th><th>Status</th></tr></thead>
<tbody>
@foreach($restaurantMenuItems as $item)
<tr>
<td><span class="table-primary">{{ $item->name }}</span></td>
<td>{{ $item->category?->name ?? '—' }}</td>
<td>{{ $item->is_vegetarian ? 'Vegetarian' : 'Non-vegetarian' }}</td>
<td>₹{{ number_format((float)$item->price,2) }}</td>
<td><span class="status-badge {{ $item->is_active ? 'good' : 'warn' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$restaurantMenuItems,'label'=>'Restaurant menu pagination','fragment'=>'restaurant-menu'])
@endif
</div>
</section>
@endsection
