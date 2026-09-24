<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Choose another room | Hotel Vastu Premium</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f6f3ef;color:#241f1b}main{max-width:820px;margin:auto;padding:32px 20px 64px}.notice,.error{padding:14px 16px;border-radius:10px}.error{background:#fff0f0;color:#791717}.notice{background:#eef6ee}.stay{margin:20px 0;padding:16px;border:1px solid #ded8d1;border-radius:12px;background:#fff}.cards{display:grid;gap:14px}.card{background:#fff;border:1px solid #ded8d1;border-radius:14px;padding:18px}.price{font-size:1.25rem;font-weight:800}.muted{color:#6d635c}button,a.button{display:inline-block;min-height:44px;line-height:44px;border:0;border-radius:8px;padding:0 18px;background:#2b211b;color:#fff;text-decoration:none;font:inherit;cursor:pointer}
</style>
</head>
<body>
@include('partials.toast')
<main>
<p>Hotel Vastu Premium</p>
<h1>Choose another room</h1>

<p class="error">{{ $message }}</p>

<div class="stay">
<strong>Your stay details are saved</strong><br>
{{ $checkIn->format('d M Y') }} → {{ $checkOut->format('d M Y') }} ·
{{ $adults }} adult(s)@if($children), {{ $children }} child(ren)@endif
</div>

@if(count($alternatives))
<div class="cards">
@foreach($alternatives as $offer)
<article class="card">
<h2>{{ $offer['room_type']->name }}</h2>
<p>{{ $offer['rate_plan']->name }} · {{ $offer['rooms'] }} room(s)</p>
<p class="price">₹{{ number_format((float) $offer['quote']['total'], 2) }} total</p>
<p class="muted">Room ₹{{ number_format((float) $offer['quote']['subtotal'], 2) }} + tax ₹{{ number_format((float) $offer['quote']['tax'], 2) }}</p>
<form method="post" action="{{ route('booking.holds.store') }}">@csrf
<input type="hidden" name="check_in" value="{{ $checkIn->toDateString() }}">
<input type="hidden" name="check_out" value="{{ $checkOut->toDateString() }}">
<input type="hidden" name="room_type_id" value="{{ $offer['room_type']->id }}">
<input type="hidden" name="rate_plan_id" value="{{ $offer['rate_plan']->id }}">
<input type="hidden" name="rooms" value="{{ $offer['rooms'] }}">
<input type="hidden" name="adults" value="{{ $adults }}">
<input type="hidden" name="children" value="{{ $children }}">
<input type="hidden" name="website_handoff" value="1">
<button type="submit">Choose {{ $offer['room_type']->name }}</button>
</form>
</article>
@endforeach
</div>
@else
<p class="notice">No alternative room is available for these dates right now. Please try different dates or contact the hotel.</p>
<p><a class="button" href="{{ route('booking.search') }}">Change dates</a></p>
@endif
</main>
</body>
</html>
