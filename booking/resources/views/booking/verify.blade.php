<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Verify mobile | Hotel Vastu Premium</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f6f3ef;color:#241f1b}main{max-width:560px;margin:auto;padding:32px 20px 64px}.panel{background:#fff;border:1px solid #ded8d1;border-radius:16px;padding:24px}label{display:grid;gap:8px;font-weight:700}input,button{min-height:46px;font:inherit}input{border:1px solid #b9b0a7;border-radius:8px;padding:0 12px;font-size:1.1rem;letter-spacing:.12em}button{border:0;border-radius:8px;padding:0 18px;background:#2b211b;color:#fff;cursor:pointer}.error{padding:12px 14px;border-radius:8px;background:#fff0f0;color:#791717}.muted{color:#6d635c}
</style>
</head>
<body>
<main>
<p>Hotel Vastu Premium</p>
<h1>Verify your mobile</h1>
<p>We sent a 6-digit verification code to <strong>{{ $verification->phone }}</strong>.</p>
@if(config('services.sms.driver','log')==='log')
<p class="muted">Local mode: the OTP is written to <code>storage/logs/laravel.log</code>.</p>
@endif
@if ($errors->any()) <div class="error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
<section class="panel">
<form method="post" action="{{ route('booking.otp.verify',['token'=>$hold->token]) }}">@csrf
<label>Verification code
<input name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus>
</label>
<p><button type="submit">Verify & confirm booking</button></p>
</form>
</section>
</main>
</body>
</html>
