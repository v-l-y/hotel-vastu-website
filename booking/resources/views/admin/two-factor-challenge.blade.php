<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Two-factor authentication | Hotel Vastu Premium</title>
<style>body{font-family:system-ui,sans-serif;background:#f5f2ee;color:#231e1a;margin:0}main{max-width:440px;margin:8vh auto;padding:20px}.panel{background:#fff;border:1px solid #ddd6cf;border-radius:14px;padding:24px}label{display:grid;gap:6px;margin:14px 0;font-weight:600}input,button{font:inherit;min-height:44px}input{padding:0 10px;border:1px solid #b9afa6;border-radius:8px}button{border:0;border-radius:8px;background:#2b211b;color:#fff;padding:0 16px}.error{background:#fff0f0;color:#771717;padding:10px;border-radius:8px}.muted{color:#6f675f}</style>
</head>
<body><main><section class="panel">
<p>Hotel Vastu Premium</p>
<h1>Authentication code</h1>
<p class="muted">Enter the six-digit code from the authenticator app for {{ $admin->email }}.</p>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('admin.two-factor.verify') }}">@csrf
<label>6-digit code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required></label>
<button type="submit">Verify</button>
</form>
</section></main></body></html>
