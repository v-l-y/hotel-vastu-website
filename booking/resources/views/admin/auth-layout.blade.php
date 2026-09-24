<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title', 'Admin access') | Hotel Vastu Premium</title>
<style>
:root{color-scheme:light;--ink:#231e1a;--muted:#6f675f;--line:#ddd6cf;--paper:#fff;--canvas:#f5f2ee;--brand:#211a16;--brand-2:#3a2a21;--danger:#771717}
*{box-sizing:border-box}
body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:var(--canvas);color:var(--ink);min-height:100vh}
.auth-shell{min-height:100vh;display:grid;grid-template-columns:minmax(320px,.85fr) minmax(420px,1.15fr)}
.auth-brand{background:linear-gradient(145deg,var(--brand) 0%,var(--brand-2) 100%);color:#fff;padding:clamp(32px,6vw,72px);display:flex;flex-direction:column;justify-content:space-between;gap:36px}
.auth-mark{display:inline-flex;width:52px;height:52px;border-radius:16px;align-items:center;justify-content:center;background:#fff;color:var(--brand);font-weight:900;letter-spacing:.04em}
.auth-brand h1{font-size:clamp(2rem,4vw,3.4rem);line-height:1.02;margin:18px 0 14px;max-width:620px}
.auth-brand p{margin:0;color:#eadfd7;max-width:560px;font-size:1.03rem;line-height:1.6}
.auth-brand-foot{font-size:.9rem;color:#d8cbc2}
.auth-main{display:flex;align-items:center;justify-content:center;padding:32px}
.auth-card{width:min(100%,480px);background:var(--paper);border:1px solid var(--line);border-radius:22px;padding:30px;box-shadow:0 24px 60px rgba(35,30,26,.08)}
.auth-eyebrow{font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#806b5c}
.auth-card h2{font-size:clamp(1.65rem,3vw,2.15rem);margin:8px 0 8px}
.auth-copy{margin:0 0 22px;color:var(--muted);line-height:1.55}
label{display:grid;gap:7px;margin:14px 0;font-weight:700}
input,button{font:inherit;min-height:46px}
input{width:100%;padding:0 12px;border:1px solid #b9afa6;border-radius:10px;background:#fff;color:var(--ink)}
input:focus{outline:3px solid rgba(142,113,89,.18);border-color:#8e7159}
button{border:0;border-radius:10px;background:var(--brand);color:#fff;padding:0 18px;font-weight:800;cursor:pointer}
button:hover{background:#382b24}
button:focus-visible{outline:3px solid rgba(142,113,89,.32);outline-offset:2px}
.auth-submit{width:100%;margin-top:8px}
.password-field{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}
.password-field button{background:#eee8e2;color:var(--ink);min-width:76px}
.alert-error{background:#fff0f0;color:var(--danger);border:1px solid #f0cccc;padding:12px 14px;border-radius:10px;margin:14px 0}
.auth-meta{margin-top:18px;padding-top:16px;border-top:1px solid #eee8e2;color:var(--muted);font-size:.9rem;line-height:1.5}
.code-input{text-align:center;letter-spacing:.35em;font-size:1.25rem;font-weight:800}
@media(max-width:820px){.auth-shell{grid-template-columns:1fr}.auth-brand{padding:28px}.auth-brand h1{font-size:2rem}.auth-brand-foot{display:none}.auth-main{padding:22px}.auth-card{padding:24px}}
</style>
@stack('styles')
</head>
<body>
@include('partials.toast')
<div class="auth-shell">
<section class="auth-brand" aria-label="Hotel Vastu Admin">
<div>
<div class="auth-mark">HV</div>
<h1>Hotel operations, in one secure workspace.</h1>
<p>Manage front desk activity, restaurant operations, payments, reporting, setup and access controls from Hotel Vastu Admin.</p>
</div>
<div class="auth-brand-foot">Hotel Vastu Premium · Authorized staff access only</div>
</section>
<main class="auth-main">
@yield('content')
</main>
</div>
@stack('scripts')
</body>
</html>
