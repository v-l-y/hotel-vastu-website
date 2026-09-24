@extends('admin.layout')
@section('title','Setup')
@section('content')
<section class="page-hero">
<div><h1>Hotel setup</h1><p>Configure inventory, rates, promotions, taxes and restaurant masters without changing operational history.</p></div>
</section>

<nav class="tabs" aria-label="Setup sections">
<a href="#rooms">Rooms</a>
<a href="#rates">Rates</a>
<a href="#promotions">Promotions</a>
<a href="#taxes">Taxes</a>
<a href="#restaurant">Restaurant</a>
</nav>

<section class="panel" id="rooms">
<div class="section-head"><div><h2>Room types</h2><p class="muted">Capacity, base pricing reference and availability status.</p></div></div>
@foreach($roomTypes as $type)
<form class="grid" method="post" action="{{ route('admin.setup.room-types.update',$type) }}">@csrf
<div><strong>{{ $type->name }}</strong><p class="muted">{{ $type->code }}</p></div>
<label>Base rate<input type="number" step="0.01" min="0" name="base_rate" value="{{ $type->base_rate }}"></label>
<label>Max adults<input type="number" min="1" name="max_adults" value="{{ $type->max_adults }}"></label>
<label>Max children<input type="number" min="0" name="max_children" value="{{ $type->max_children }}"></label>
<label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_active" value="1" @checked($type->is_active)> Active</label>
<div><button>Save {{ $type->name }}</button></div>
</form>
@if(!$loop->last)<hr style="border:0;border-top:1px solid #ebe5df;margin:18px 0">@endif
@endforeach
</section>

<section class="panel">
<div class="section-head"><div><h2>Physical rooms & maintenance blocks</h2><p class="muted">Create physical inventory and temporarily block rooms from sale.</p></div></div>
<form class="grid" method="post" action="{{ route('admin.setup.rooms.store') }}">@csrf
<label>Room type<select name="room_type_id" required>@foreach($roomTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></label>
<label>Room number<input name="number" required></label>
<label>Floor<input name="floor"></label>
<div><button>Add room</button></div>
</form>
<p class="muted">Current rooms: {{ $rooms->pluck('number')->join(', ') ?: 'none' }}</p>
<hr style="border:0;border-top:1px solid #ebe5df;margin:18px 0">
<form class="grid" method="post" action="{{ route('admin.setup.room-blocks.store') }}">@csrf
<label>Room<select name="room_id" required>@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->number }} · {{ $room->roomType->name }}</option>@endforeach</select></label>
<label>From<input type="date" name="starts_on" required></label>
<label>Until (checkout-style end)<input type="date" name="ends_on" required></label>
<label>Reason<input name="reason"></label>
<div><button>Block room</button></div>
</form>
@if($roomBlocks->isNotEmpty())
<div class="section-list" style="margin-top:16px">
@foreach($roomBlocks as $block)
<div class="compact-row"><div><strong>Room {{ $block->room->number }}</strong><br><span class="muted">{{ $block->starts_on->format('d M Y') }} → {{ $block->ends_on->format('d M Y') }} · {{ $block->reason ?: 'No reason' }}</span></div><form method="post" action="{{ route('admin.setup.room-blocks.close',$block) }}">@csrf<button type="submit">Close block</button></form></div>
@endforeach
</div>
@endif
</section>

<section class="panel" id="rates">
<div class="section-head"><div><h2>Rate plans & dated rates</h2><p class="muted">Create sellable plans and date-bound room pricing.</p></div></div>
<form class="grid" method="post" action="{{ route('admin.setup.rate-plans.store') }}">@csrf
<label>Name<input name="name" required></label><label>Code<input name="code"></label>
<label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="includes_breakfast" value="1"> Breakfast included</label>
<div><button>Add rate plan</button></div>
</form>
<hr style="border:0;border-top:1px solid #ebe5df;margin:18px 0">
<form class="grid" method="post" action="{{ route('admin.setup.room-rates.store') }}">@csrf
<label>Room type<select name="room_type_id">@foreach($roomTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></label>
<label>Rate plan<select name="rate_plan_id">@foreach($ratePlans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select></label>
<label>From<input type="date" name="starts_on" required></label><label>To<input type="date" name="ends_on" required></label>
<label>Nightly rate<input type="number" step="0.01" min="0" name="nightly_rate" required></label>
<label>Min stay<input type="number" min="1" name="min_stay" value="1" required></label>
<label>Max stay<input type="number" min="1" name="max_stay"></label>
<div><button>Add dated rate</button></div>
</form>
</section>

<section class="panel" id="promotions">
<div class="section-head"><div><h2>Promo codes</h2><p class="muted">Controlled public/desk discounts with dates, minimum spend and usage limits.</p></div></div>
<form class="grid" method="post" action="{{ route('admin.setup.promotion-codes.store') }}">@csrf
<label>Code<input name="code" maxlength="40" placeholder="WELCOME10" required></label>
<label>Name<input name="name" maxlength="120" placeholder="Welcome offer" required></label>
<label>Discount type<select name="discount_type"><option value="percent">Percent</option><option value="fixed">Fixed amount</option></select></label>
<label>Value<input type="number" step="0.01" min="0.01" name="discount_value" required></label>
<label>Max discount<input type="number" step="0.01" min="0.01" name="max_discount" placeholder="Optional cap"></label>
<label>Minimum room subtotal<input type="number" step="0.01" min="0" name="min_subtotal" value="0"></label>
<label>Active from<input type="date" name="starts_on"></label><label>Active until<input type="date" name="ends_on"></label>
<label>Usage limit<input type="number" min="1" name="usage_limit" placeholder="Optional"></label>
<div><button>Create promo code</button></div>
</form>
@if($promotionCodes->isNotEmpty())
<div class="section-list" style="margin-top:18px">
@foreach($promotionCodes as $promo)
<div class="compact-row"><div><strong>{{ $promo->code }}</strong> · {{ $promo->name }}<br><span class="muted">{{ $promo->discount_type === 'percent' ? number_format((float)$promo->discount_value,2).'%' : '₹'.number_format((float)$promo->discount_value,2) }} off@if($promo->max_discount) · max ₹{{ number_format((float)$promo->max_discount,2) }}@endif · used {{ $promo->times_used }}{{ $promo->usage_limit ? '/'.$promo->usage_limit : '' }}</span></div><form method="post" action="{{ route('admin.setup.promotion-codes.status',$promo) }}">@csrf<input type="hidden" name="is_active" value="{{ $promo->is_active ? 0 : 1 }}"><button type="submit">{{ $promo->is_active ? 'Deactivate' : 'Activate' }}</button></form></div>
@endforeach
</div>
@endif
</section>

<section class="panel" id="taxes">
<div class="section-head"><div><h2>Tax rules</h2><p class="muted">Define effective hotel and restaurant tax percentages.</p></div></div>
<form class="grid" method="post" action="{{ route('admin.setup.tax-rules.store') }}">@csrf
<label>Name<input name="name" required></label>
<label>Applies to<select name="applies_to"><option value="hotel">Hotel</option><option value="restaurant">Restaurant</option><option value="all">All</option></select></label>
<label>Rate %<input type="number" step="0.0001" min="0" max="100" name="rate_percent" required></label>
<label>Effective from<input type="date" name="effective_from"></label>
<label>Effective to<input type="date" name="effective_to"></label>
<div><button>Add tax rule</button></div>
</form>
</section>

<section class="panel" id="restaurant">
<div class="section-head"><div><h2>Restaurant setup</h2><p class="muted">Manage menu categories, items and physical dining tables.</p></div></div>
<div class="grid">
<form class="panel" method="post" action="{{ route('admin.setup.restaurant-categories.store') }}">@csrf
<h3>Category</h3><label>Category name<input name="name" required></label><button>Add category</button>
</form>
<form class="panel" method="post" action="{{ route('admin.setup.restaurant-menu-items.store') }}">@csrf
<h3>Menu item</h3>
<label>Category<select name="restaurant_category_id">@foreach($restaurantCategories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
<label>Item name<input name="name" required></label><label>Price<input type="number" step="0.01" min="0" name="price" required></label>
<label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_vegetarian" value="1"> Vegetarian</label>
<button>Add menu item</button>
</form>
<form class="panel" method="post" action="{{ route('admin.setup.restaurant-tables.store') }}">@csrf
<h3>Dining table</h3><label>Code<input name="code" required></label><label>Name<input name="name" required></label><label>Capacity<input type="number" min="1" name="capacity"></label><button>Add table</button>
</form>
</div>
</section>
@endsection
