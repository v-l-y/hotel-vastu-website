<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Guest details | Hotel Vastu Premium</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f6f3ef;color:#241f1b}main{max-width:760px;margin:auto;padding:32px 20px 64px}.panel{background:#fff;border:1px solid #ded8d1;border-radius:16px;padding:24px}.summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:20px 0}.summary div{border:1px solid #e2ddd7;border-radius:10px;padding:12px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}label{display:grid;gap:7px;font-weight:600}input,textarea,button{font:inherit}input,textarea{border:1px solid #b9b0a7;border-radius:8px;padding:10px 12px}button{min-height:44px;border:0;border-radius:8px;padding:0 18px;background:#2b211b;color:#fff;cursor:pointer}.full{grid-column:1/-1}.error{padding:12px 14px;border-radius:8px;background:#fff0f0;color:#791717}@media(max-width:640px){.summary,.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<main>
<p>Hotel Vastu Premium</p>
<h1>Guest details</h1>
<p>Your selected inventory is held until {{ $hold->expires_at->format('d M Y, h:i A') }}.</p>

<div class="summary">
<div><strong>{{ $hold->roomType->name }}</strong><br>{{ $hold->quantity }} room(s)</div>
<div><strong>{{ $hold->check_in_date->format('d M Y') }} → {{ $hold->check_out_date->format('d M Y') }}</strong><br>{{ $hold->adults }} adult(s), {{ $hold->children }} child(ren)</div>
</div>

@if ($errors->any()) <div class="error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

<section class="panel">
<form class="grid" method="post" action="{{ route('booking.confirm', ['token' => $hold->token]) }}">@csrf
<label>First name<input name="first_name" autocomplete="given-name" required value="{{ old('first_name') }}"></label>
<label>Last name<input name="last_name" autocomplete="family-name" value="{{ old('last_name') }}"></label>
<label>Phone<input name="phone" inputmode="tel" autocomplete="tel" required value="{{ old('phone') }}"></label>
<label>Email<input name="email" type="email" autocomplete="email" value="{{ old('email') }}"></label>
<label class="full">Special request<textarea name="special_request" rows="4">{{ old('special_request') }}</textarea></label>
<div class="full"><button type="submit">Confirm booking</button></div>
</form>
</section>
</main>
</body>
</html>
