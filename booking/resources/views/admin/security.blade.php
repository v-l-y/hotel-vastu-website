@extends('admin.layout')
@section('title','Security')
@section('content')
<section class="page-hero">
<div><h1>Security</h1><p>Manage two-factor authentication and review the protections applied to your admin session.</p></div>
<span class="status-badge {{ $admin->hasTwoFactorEnabled() ? 'good' : 'warn' }}">{{ $admin->hasTwoFactorEnabled() ? '2FA enabled' : '2FA not enabled' }}</span>
</section>

<section class="panel">
<div class="section-head">
<div><h2>Two-factor authentication</h2><p class="muted">Account: {{ $admin->email }}</p></div>
</div>

@if($admin->hasTwoFactorEnabled())
<div class="notice"><strong>Two-factor authentication is enabled.</strong> A six-digit authenticator code is required after your password.</div>
<form class="grid" method="post" action="{{ route('admin.security.two-factor.disable') }}">@csrf
<label>Current password<input type="password" name="password" autocomplete="current-password" required></label>
<label>Authenticator code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
<div><button class="danger" type="submit">Disable 2FA</button></div>
</form>
@else
<p class="muted">Use any TOTP-compatible authenticator app, such as Google Authenticator, Microsoft Authenticator or 1Password.</p>
@if(!$setupSecret)
<form method="post" action="{{ route('admin.security.two-factor.setup') }}">@csrf
<button type="submit">Generate setup key</button>
</form>
@else
<div class="panel" style="background:#fcfaf8">
<div class="section-head"><div><h2 style="font-size:1rem">Authenticator setup</h2><p class="muted">Add this key to your authenticator app, then verify below.</p></div></div>
<p><strong>Manual setup key</strong></p><p><code>{{ $setupSecret }}</code></p>
<details><summary>Show provisioning URI</summary><p><code>{{ $provisioningUri }}</code></p></details>
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
<div class="section-head"><div><h2>Session protection</h2><p class="muted">How active sessions are protected.</p></div></div>
<div class="cards">
<div class="card"><strong>Revocation</strong><span>Password, access or 2FA resets revoke older sessions automatically.</span></div>
<div class="card"><strong>Cookies</strong><span>Production sessions use Secure and HttpOnly cookies.</span></div>
<div class="card"><strong>Storage</strong><span>Production session state is designed for encrypted Redis-backed storage.</span></div>
</div>
</section>
@endsection
