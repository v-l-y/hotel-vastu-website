@extends('admin.auth-layout')
@section('title','Admin login')
@section('content')
<section class="auth-card">
<div class="auth-eyebrow">Hotel Vastu Premium</div>
<h2>Sign in to Admin</h2>
<p class="auth-copy">Use your staff account to continue to the operational dashboard.</p>

<form method="post" action="{{ route('admin.login.submit') }}">
@csrf
<label>Email address
<input type="email" name="email" autocomplete="username" inputmode="email" required autofocus value="{{ old('email') }}" placeholder="name@example.com">
</label>
<label>Password
<div class="password-field">
<input id="admin-password" type="password" name="password" autocomplete="current-password" required>
<button type="button" data-password-toggle aria-controls="admin-password" aria-pressed="false">Show</button>
</div>
</label>
<button class="auth-submit" type="submit">Sign in securely</button>
</form>

<div class="auth-meta">Login attempts are rate-limited and security events are recorded. Accounts with two-factor authentication enabled will be asked for an authenticator code next.</div>
</section>
@endsection

@push('scripts')
<script>
(() => {
  const button = document.querySelector('[data-password-toggle]');
  const input = document.getElementById('admin-password');
  if (!button || !input) return;
  button.addEventListener('click', () => {
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.textContent = show ? 'Hide' : 'Show';
    button.setAttribute('aria-pressed', show ? 'true' : 'false');
    input.focus();
  });
})();
</script>
@endpush
