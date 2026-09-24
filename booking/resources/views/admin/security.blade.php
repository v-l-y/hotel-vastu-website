@extends('admin.layout')
@section('title','Security')
@section('content')
<h1>Security</h1>

<section class="panel">
<h2>Two-factor authentication</h2>
<p>Account: <strong>{{ $admin->email }}</strong></p>

@if($admin->hasTwoFactorEnabled())
<p class="notice">Two-factor authentication is enabled. A six-digit authenticator code is required after your password.</p>
<form class="grid" method="post" action="{{ route('admin.security.two-factor.disable') }}">@csrf
<label>Current password<input type="password" name="password" autocomplete="current-password" required></label>
<label>Authenticator code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
<div><button class="danger" type="submit">Disable 2FA</button></div>
</form>
@else
<p class="muted">Use an authenticator app that supports TOTP, such as Google Authenticator, Microsoft Authenticator or 1Password.</p>

@if(!$setupSecret)
<form method="post" action="{{ route('admin.security.two-factor.setup') }}">@csrf
<button type="submit">Generate setup key</button>
</form>
@else
<div class="panel">
<p><strong>Manual setup key</strong></p>
<p><code>{{ $setupSecret }}</code></p>
<p class="muted">Add this key to your authenticator app. The provisioning URI is shown for compatible password managers and authenticator tools.</p>
<p><code>{{ $provisioningUri }}</code></p>
</div>
<form class="grid" method="post" action="{{ route('admin.security.two-factor.enable') }}">@csrf
<label>Current password<input type="password" name="password" autocomplete="current-password" required></label>
<label>6-digit code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
<div><button type="submit">Verify & enable 2FA</button></div>
</form>
@endif
@endif
</section>

<section class="panel">
<h2>Session protection</h2>
<p class="muted">Password resets, role/access changes and 2FA resets revoke older sessions automatically. Production sessions use encrypted Redis storage with Secure, HttpOnly cookies.</p>
</section>
@endsection
