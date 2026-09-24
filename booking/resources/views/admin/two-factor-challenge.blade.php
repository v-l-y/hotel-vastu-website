@extends('admin.auth-layout')
@section('title','Two-factor authentication')
@section('content')
<section class="auth-card">
<div class="auth-eyebrow">Identity verification</div>
<h2>Authenticator code</h2>
<p class="auth-copy">Enter the six-digit code from your authenticator app for <strong>{{ $admin->email }}</strong>.</p>

<form method="post" action="{{ route('admin.two-factor.verify') }}">
@csrf
<label>6-digit code
<input class="code-input" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required aria-describedby="code-help">
</label>
<p id="code-help" class="auth-copy" style="font-size:.9rem;margin-top:-4px">Use the current code shown in your authenticator app.</p>
<button class="auth-submit" type="submit">Verify and continue</button>
</form>

<div class="auth-meta">This verification is required because two-factor authentication is enabled for your account.</div>
</section>
@endsection
