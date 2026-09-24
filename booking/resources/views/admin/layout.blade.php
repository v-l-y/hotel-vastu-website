<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title', 'Hotel Admin') | Hotel Vastu Premium</title>
<style>
:root{color-scheme:light;--ink:#231e1a;--muted:#6f675f;--line:#ddd6cf;--line-soft:#ebe5df;--paper:#fff;--canvas:#f5f2ee;--brand:#211a16;--brand-2:#3a2a21;--accent:#8e7159;--danger:#7d1c1c}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:var(--canvas);color:var(--ink)}
a{color:inherit}
button,input,select,textarea{font:inherit}
button{cursor:pointer}
.skip-link{position:fixed;left:12px;top:12px;z-index:9999;transform:translateY(-180%);background:#fff;color:#211a16;padding:10px 14px;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.18)}
.skip-link:focus{transform:translateY(0)}
.admin-app{min-height:100vh;display:grid;grid-template-columns:260px minmax(0,1fr)}
.admin-sidebar{position:sticky;top:0;height:100vh;background:linear-gradient(180deg,#211a16 0%,#2c211b 100%);color:#fff;padding:20px 16px;display:flex;flex-direction:column;gap:18px;overflow:auto}
.admin-brand{display:flex;gap:11px;align-items:center;padding:2px 6px 10px}
.admin-brand-mark{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#fff;color:#211a16;font-weight:900;letter-spacing:.04em;flex:0 0 auto}
.admin-brand strong{display:block;font-size:1rem}
.admin-brand span{display:block;color:#cfc1b8;font-size:.78rem;margin-top:2px}
.admin-user-card{border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);border-radius:14px;padding:12px}
.admin-user-card strong{display:block}
.admin-user-card span{display:block;color:#d7cbc2;font-size:.84rem;margin-top:3px;text-transform:capitalize}
.admin-nav-group{display:grid;gap:6px}
.admin-nav-title{padding:5px 10px;color:#aa9a8f;font-size:.7rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
.admin-nav{display:grid;gap:5px}
.admin-nav-link{display:flex;align-items:center;gap:10px;min-height:42px;padding:9px 11px;border-radius:10px;color:#eee5df;text-decoration:none;font-weight:700}
.admin-nav-link:hover,.admin-nav-link:focus-visible{background:rgba(255,255,255,.08);outline:none}
.admin-nav-link.active{background:#fff;color:#211a16;box-shadow:0 8px 20px rgba(0,0,0,.16)}
.admin-nav-icon{width:28px;height:28px;border-radius:9px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.09);font-size:.72rem;font-weight:900;letter-spacing:.03em;flex:0 0 auto}
.admin-nav-link.active .admin-nav-icon{background:#eee8e2}
.admin-sidebar-foot{margin-top:auto;display:grid;gap:10px}
.admin-logout{width:100%;min-height:42px;border:1px solid rgba(255,255,255,.16);border-radius:10px;background:rgba(255,255,255,.07);color:#fff;font-weight:800}
.admin-logout:hover{background:rgba(255,255,255,.13)}
.admin-main{min-width:0}
.admin-topbar{min-height:70px;padding:14px 24px;border-bottom:1px solid var(--line);background:rgba(245,242,238,.94);backdrop-filter:blur(10px);display:flex;gap:16px;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:30}
.admin-topbar-title{min-width:0}
.admin-topbar-title span{display:block;color:#85796f;font-size:.76rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
.admin-topbar-title strong{display:block;font-size:1.05rem;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.admin-topbar-meta{display:flex;gap:8px;align-items:center;color:#6f675f;font-size:.9rem}
.admin-content{max-width:1480px;margin:0 auto;padding:24px}
.panel{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;margin:0 0 18px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px}
.card strong{display:block;font-size:1.5rem}
label{display:grid;gap:6px;font-weight:700}
input,select,textarea,button{min-height:42px}
input,select,textarea{border:1px solid #b9afa6;border-radius:9px;padding:8px 10px;background:#fff;color:var(--ink);max-width:100%}
input:focus,select:focus,textarea:focus{outline:3px solid rgba(142,113,89,.16);border-color:var(--accent)}
button{border:0;border-radius:9px;padding:8px 14px;background:#2b211b;color:#fff;font-weight:800}
button:hover{background:#3b2e27}
button:focus-visible,.button-link:focus-visible{outline:3px solid rgba(142,113,89,.28);outline-offset:2px}
button:disabled{opacity:.55;cursor:not-allowed}
.danger{background:var(--danger)}
.notice,.error{padding:12px 14px;border-radius:10px;margin-bottom:16px;border:1px solid transparent}
.notice{background:#eaf5ea;border-color:#cce4d0;color:#245d2d}
.error{background:#fff0f0;border-color:#f0cccc;color:#771717}
.error ul{margin:0;padding-left:18px}
.muted{color:var(--muted)}
.full{grid-column:1/-1}
.table-wrap{overflow:auto;border:1px solid var(--line-soft);border-radius:12px}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;border-bottom:1px solid #e7e1dc;padding:10px;vertical-align:top}
th{font-size:.78rem;letter-spacing:.04em;text-transform:uppercase;color:#776d65;background:#faf8f6}
tbody tr:last-child td{border-bottom:0}
.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:18px}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}
.tabs a,.button-link{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 14px;border:1px solid #cfc5bc;border-radius:9px;background:#fff;color:#2b211b;text-decoration:none;font-weight:800}
.tabs a.active,.button-link.primary{background:#2b211b;color:#fff;border-color:#2b211b}
.button-link.danger{background:#7d1c1c;color:#fff;border-color:#7d1c1c}
.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}
.metric-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px}
.metric-card strong{display:block;font-size:1.7rem;margin-top:4px}
.status-badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;background:#eee8e2;font-size:.82rem;font-weight:800}
.status-badge.good{background:#e8f5ea;color:#245d2d}.status-badge.warn{background:#fff2df;color:#7a4a08}
.page-hero{background:linear-gradient(135deg,#241b16 0%,#3a2a21 100%);color:#fff;border-radius:20px;padding:22px;margin-bottom:18px;display:flex;gap:18px;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;box-shadow:0 12px 30px rgba(35,30,26,.1)}
.page-hero h1{margin:0 0 6px;font-size:clamp(1.65rem,3vw,2.3rem)}
.page-hero p{margin:0;color:#e8ddd4;max-width:760px;line-height:1.5}
.page-hero .button-link{background:#fff;border-color:#fff;color:#211a16}
.section-head{display:flex;gap:12px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px}
.section-head h2{margin:0 0 4px}.section-head p{margin:0}
.empty-state{border:1px dashed #d8cfc7;border-radius:12px;padding:22px;text-align:center;background:#fcfaf8;color:#6d655e}
.stat-card{background:#fff;border:1px solid #e2dbd4;border-radius:16px;padding:16px;position:relative;overflow:hidden}
.stat-card:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:#8e7159}
.stat-card strong{display:block;font-size:1.8rem;margin-top:7px}
.stat-card small{display:block;color:#776e66;margin-top:6px}
.reservation-card,.stay-card{border:1px solid #e3ddd7;border-radius:12px;padding:16px;margin-bottom:12px}
.reservation-card:target,.stay-card:target{outline:2px solid #8e7159;outline-offset:2px}
.reservation-head,.stay-head{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}
.reservation-meta,.stay-meta{display:flex;gap:8px 14px;flex-wrap:wrap;margin:8px 0;color:#5f5750}
.section-list{display:grid;gap:12px}
.compact-row{display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap;padding:12px 0;border-bottom:1px solid #ece6e0}
.compact-row:last-child{border-bottom:0}
.search-bar{display:flex;gap:8px;flex:1;max-width:620px}.search-bar input{flex:1}
.item-row{display:grid;grid-template-columns:2fr .7fr 2fr auto;gap:8px;margin-bottom:8px;align-items:end}
code{word-break:break-all}
@media(max-width:1100px){.cards,.metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){
.admin-app{grid-template-columns:1fr}
.admin-sidebar{position:sticky;height:auto;z-index:50;padding:10px 14px;gap:10px}
.admin-brand{padding:0}.admin-brand span,.admin-user-card,.admin-nav-title{display:none}
.admin-nav-group{display:block}.admin-nav{display:flex;overflow:auto;padding-bottom:2px;scrollbar-width:thin}
.admin-nav-link{white-space:nowrap;flex:0 0 auto}.admin-nav-icon{display:none}
.admin-sidebar-foot{margin:0;position:absolute;right:14px;top:10px}
.admin-logout{width:auto;padding-inline:12px}
.admin-topbar{top:62px;padding:12px 16px;min-height:58px}
.admin-content{padding:18px}
}
@media(max-width:640px){
.grid,.cards,.item-row,.metric-grid{grid-template-columns:1fr}
.search-bar{max-width:none;width:100%}
.admin-brand strong{font-size:.92rem}
.admin-sidebar{padding-right:88px}
.admin-content{padding:14px}
.admin-topbar-meta{display:none}
.page-hero{padding:18px;border-radius:16px}
table{display:block;overflow-x:auto;width:100%}
}
</style>
@stack('styles')
</head>
<body>
<a class="skip-link" href="#admin-content">Skip to content</a>
@php
$currentAdmin = request()->attributes->get('admin_user');
$roleLabel = match($currentAdmin?->role) {
    'front_desk' => 'Front Desk',
    'restaurant' => 'Restaurant',
    'kitchen' => 'Kitchen',
    'accounts' => 'Accounts',
    'administrator' => 'Administrator',
    default => ucfirst(str_replace('_', ' ', (string) $currentAdmin?->role)),
};
@endphp
<div class="admin-app">
<aside class="admin-sidebar">
<div class="admin-brand">
<div class="admin-brand-mark">HV</div>
<div><strong>Hotel Vastu Admin</strong><span>Operations console</span></div>
</div>

<div class="admin-user-card">
<strong>{{ $currentAdmin?->name ?? 'Admin user' }}</strong>
<span>{{ $roleLabel }}</span>
</div>

<div class="admin-nav-group">
<div class="admin-nav-title">Operations</div>
<nav class="admin-nav" aria-label="Admin operations">
<a class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif><span class="admin-nav-icon">DB</span><span>Dashboard</span></a>
@if(in_array($currentAdmin?->role,['administrator','front_desk'],true))
<a class="admin-nav-link {{ request()->routeIs('admin.front-desk*') ? 'active' : '' }}" href="{{ route('admin.front-desk') }}" @if(request()->routeIs('admin.front-desk*')) aria-current="page" @endif><span class="admin-nav-icon">FD</span><span>Front Desk</span></a>
@endif
@if(in_array($currentAdmin?->role,['administrator','restaurant','kitchen'],true))
<a class="admin-nav-link {{ request()->routeIs('admin.restaurant*') ? 'active' : '' }}" href="{{ route('admin.restaurant') }}" @if(request()->routeIs('admin.restaurant*')) aria-current="page" @endif><span class="admin-nav-icon">KT</span><span>{{ $currentAdmin?->role === 'kitchen' ? 'Kitchen KOT' : 'Restaurant & KOT' }}</span></a>
@endif
@if(in_array($currentAdmin?->role,['administrator','front_desk','accounts','restaurant'],true))
<a class="admin-nav-link {{ request()->routeIs('admin.payments*') || request()->routeIs('admin.invoices*') ? 'active' : '' }}" href="{{ route('admin.payments') }}" @if(request()->routeIs('admin.payments*') || request()->routeIs('admin.invoices*')) aria-current="page" @endif><span class="admin-nav-icon">₹</span><span>Payments</span></a>
@endif
@if(in_array($currentAdmin?->role,['administrator','front_desk','accounts'],true))
<a class="admin-nav-link {{ request()->routeIs('admin.reports*') ? 'active' : '' }}" href="{{ route('admin.reports') }}" @if(request()->routeIs('admin.reports*')) aria-current="page" @endif><span class="admin-nav-icon">RP</span><span>Reports</span></a>
@endif
</nav>
</div>

<div class="admin-nav-group">
<div class="admin-nav-title">Administration</div>
<nav class="admin-nav" aria-label="Admin settings">
@if($currentAdmin?->role === 'administrator')
<a class="admin-nav-link {{ request()->routeIs('admin.setup*') ? 'active' : '' }}" href="{{ route('admin.setup') }}" @if(request()->routeIs('admin.setup*')) aria-current="page" @endif><span class="admin-nav-icon">ST</span><span>Setup</span></a>
<a class="admin-nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}" href="{{ route('admin.users') }}" @if(request()->routeIs('admin.users*')) aria-current="page" @endif><span class="admin-nav-icon">UA</span><span>Users & Audit</span></a>
@endif
<a class="admin-nav-link {{ request()->routeIs('admin.security*') ? 'active' : '' }}" href="{{ route('admin.security') }}" @if(request()->routeIs('admin.security*')) aria-current="page" @endif><span class="admin-nav-icon">SC</span><span>Security</span></a>
</nav>
</div>

<div class="admin-sidebar-foot">
<form method="post" action="{{ route('admin.logout') }}">
@csrf
<button class="admin-logout" type="submit">Sign out</button>
</form>
</div>
</aside>

<div class="admin-main">
<header class="admin-topbar">
<div class="admin-topbar-title">
<span>Hotel Vastu Premium</span>
<strong>@yield('title', 'Hotel Admin')</strong>
</div>
<div class="admin-topbar-meta"><span>{{ $currentAdmin?->email }}</span><span class="status-badge">{{ $roleLabel }}</span></div>
</header>

<main class="admin-content" id="admin-content">
@if(session('status'))<div class="notice" role="status" aria-live="polite">{{ session('status') }}</div>@endif
@if($errors->any())<div class="error" role="alert" aria-live="assertive"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>
</div>
</div>
@stack('scripts')
</body>
</html>
