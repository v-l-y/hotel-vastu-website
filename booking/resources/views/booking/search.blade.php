<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Book Hotel Vastu Premium</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f6f3ef;color:#241f1b}main{max-width:920px;margin:auto;padding:32px 20px 64px}.panel{background:#fff;border:1px solid #ded8d1;border-radius:16px;padding:24px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}label{display:grid;gap:7px;font-weight:600}input,select,button{min-height:44px;font:inherit}input,select{border:1px solid #b9b0a7;border-radius:8px;padding:0 12px}button{border:0;border-radius:8px;padding:0 18px;background:#2b211b;color:#fff;cursor:pointer}.full{grid-column:1/-1}.result{margin-top:24px}.notice,.error{padding:12px 14px;border-radius:8px}.notice{background:#eef6ee}.error{background:#fff0f0;color:#791717}.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:18px 0}.metric{border:1px solid #e2ddd7;border-radius:10px;padding:14px}.metric strong{display:block;font-size:1.5rem}.price{font-size:1.3rem;font-weight:700}@media(max-width:640px){.grid,.metrics{grid-template-columns:1fr}}
</style>
</head>
<body>
<main>
<p>Hotel Vastu Premium</p>
<h1>Check room availability</h1>
<p>The public hotel website remains separate from this booking application.</p>

@if (session('status')) <p class="notice">{{ session('status') }}</p> @endif
@if ($errors->any()) <div class="error"><strong>Please fix the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

<section class="panel">
<form class="grid" method="get" action="{{ route('booking.availability') }}">
<label>Check-in<input type="date" name="check_in" required value="{{ old('check_in', $search['check_in'] ?? request('check_in')) }}"></label>
<label>Check-out<input type="date" name="check_out" required value="{{ old('check_out', $search['check_out'] ?? request('check_out')) }}"></label>
<label>Room type<select name="room_type_id" required><option value="">Choose a room</option>@foreach ($roomTypes as $roomType)<option value="{{ $roomType->id }}" @selected((string) old('room_type_id', $search['room_type_id'] ?? request('room_type_id')) === (string) $roomType->id)>{{ $roomType->name }}</option>@endforeach</select></label>
<label>Rate plan<select name="rate_plan_id" required><option value="">Choose a rate plan</option>@foreach ($ratePlans as $ratePlan)<option value="{{ $ratePlan->id }}" @selected((string) old('rate_plan_id', $search['rate_plan_id'] ?? request('rate_plan_id')) === (string) $ratePlan->id)>{{ $ratePlan->name }}@if($ratePlan->includes_breakfast) · Breakfast included@endif</option>@endforeach</select></label>
<label>Rooms<input type="number" name="rooms" min="1" max="10" required value="{{ old('rooms', $search['rooms'] ?? request('rooms', 1)) }}"></label>
<label>Adults<input type="number" name="adults" min="1" max="30" required value="{{ old('adults', $search['adults'] ?? request('adults', 2)) }}"></label>
<label>Children<input type="number" name="children" min="0" max="30" value="{{ old('children', $search['children'] ?? request('children', 0)) }}"></label>
<div class="full"><button type="submit">Check availability</button></div>
</form>
</section>

@if ($availability !== null)
<section class="panel result">
<h2>{{ $selectedRoomType->name }} · {{ $selectedRatePlan->name }}</h2>
<div class="metrics">
<div class="metric"><strong>{{ $availability['total_rooms'] }}</strong>Total inventory</div>
<div class="metric"><strong>{{ $availability['reserved_rooms'] }}</strong>Reserved</div>
<div class="metric"><strong>{{ $availability['held_rooms'] }}</strong>Temporarily held</div>
<div class="metric"><strong>{{ $availability['available_rooms'] }}</strong>Available</div>
</div>

@if ($quoteError)
<p class="error">{{ $quoteError }}</p>
@elseif ($quote)
<p class="price">Stay total: ₹{{ number_format($quote['total'], 2) }}</p>
<p>Room ₹{{ number_format($quote['subtotal'], 2) }} + tax ₹{{ number_format($quote['tax'], 2) }}</p>
@endif

@if (! $quoteError && $quote && $availability['available_rooms'] >= (int) ($search['rooms'] ?? 1))
<form method="post" action="{{ route('booking.holds.store') }}">@csrf
<input type="hidden" name="check_in" value="{{ $search['check_in'] }}">
<input type="hidden" name="check_out" value="{{ $search['check_out'] }}">
<input type="hidden" name="room_type_id" value="{{ $selectedRoomType->id }}">
<input type="hidden" name="rate_plan_id" value="{{ $selectedRatePlan->id }}">
<input type="hidden" name="rooms" value="{{ $search['rooms'] ?? 1 }}">
<input type="hidden" name="adults" value="{{ $search['adults'] }}">
<input type="hidden" name="children" value="{{ $search['children'] ?? 0 }}">
<button type="submit">Continue to guest details</button>
</form>
@elseif ($availability['available_rooms'] < (int) ($search['rooms'] ?? 1))
<p class="error">The requested number of rooms is not currently available.</p>
@endif
</section>
@endif
</main>
</body>
</html>
