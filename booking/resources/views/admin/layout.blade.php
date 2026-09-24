<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title', 'Hotel Admin') | Hotel Vastu Premium</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f5f2ee;color:#231e1a}header{background:#211a16;color:#fff}header .wrap{max-width:1180px;margin:auto;padding:14px 20px;display:flex;gap:18px;align-items:center;flex-wrap:wrap}.wrap{max-width:1180px;margin:auto;padding:24px 20px}nav{display:flex;gap:12px;flex-wrap:wrap}a{color:inherit}header a{color:#fff}.panel{background:#fff;border:1px solid #ddd6cf;border-radius:14px;padding:18px;margin:0 0 18px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.card{background:#fff;border:1px solid #ddd6cf;border-radius:12px;padding:16px}.card strong{display:block;font-size:1.5rem}label{display:grid;gap:6px;font-weight:600}input,select,textarea,button{font:inherit;min-height:42px}input,select,textarea{border:1px solid #b9afa6;border-radius:8px;padding:8px 10px}button{border:0;border-radius:8px;padding:8px 14px;background:#2b211b;color:#fff;cursor:pointer}.danger{background:#7d1c1c}.notice,.error{padding:12px 14px;border-radius:8px;margin-bottom:16px}.notice{background:#eaf5ea}.error{background:#fff0f0;color:#771717}.muted{color:#6f675f}.full{grid-column:1/-1}table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid #e7e1dc;padding:9px;vertical-align:top}.actions{display:flex;gap:8px;flex-wrap:wrap}.item-row{display:grid;grid-template-columns:2fr .7fr 2fr auto;gap:8px;margin-bottom:8px;align-items:end}code{word-break:break-all}@media(max-width:760px){.grid,.cards,.item-row{grid-template-columns:1fr}table{display:block;overflow:auto}}
</style>
</head>
<body>
@php($currentAdmin=request()->attributes->get('admin_user'))
<header><div class="wrap"><strong>Hotel Vastu Admin</strong><nav><a href="{{ route('admin.dashboard') }}">Dashboard</a>@if(in_array($currentAdmin?->role,['administrator','front_desk'],true))<a href="{{ route('admin.front-desk') }}">Front Desk</a>@endif @if(in_array($currentAdmin?->role,['administrator','restaurant','kitchen'],true))<a href="{{ route('admin.restaurant') }}">Restaurant</a>@endif @if(in_array($currentAdmin?->role,['administrator','front_desk','accounts','restaurant'],true))<a href="{{ route('admin.payments') }}">Payments</a>@endif @if(in_array($currentAdmin?->role,['administrator','front_desk','accounts'],true))<a href="{{ route('admin.reports') }}">Reports</a>@endif @if($currentAdmin?->role === 'administrator')<a href="{{ route('admin.setup') }}">Setup</a><a href="{{ route('admin.users') }}">Users & Audit</a>@endif <a href="{{ route('admin.security') }}">Security</a></nav><form method="post" action="{{ route('admin.logout') }}" style="margin-left:auto">@csrf<button type="submit">Logout</button></form></div></header>
<main class="wrap">
@if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
@if($errors->any())<div class="error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>
</body>
</html>
