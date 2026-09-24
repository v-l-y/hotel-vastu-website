@extends('admin.layout')
@section('title','Users & Audit')
@section('content')
@php($currentAdmin=request()->attributes->get('admin_user'))
<h1>Admin users & audit</h1>

<section class="panel">
<h2>Create admin user</h2>
<p class="muted">Passwords require 12+ characters with upper/lowercase letters, a number and a symbol.</p>
<form class="grid" method="post" action="{{ route('admin.users.store') }}">@csrf
<label>Name<input name="name" required></label>
<label>Email<input type="email" name="email" required></label>
<label>Role<select name="role">@foreach($roles as $role)<option value="{{ $role }}">{{ str_replace('_',' ',$role) }}</option>@endforeach</select></label>
<label>Password<input type="password" name="password" minlength="12" required></label>
<label>Confirm password<input type="password" name="password_confirmation" minlength="12" required></label>
<div><button>Create user</button></div>
</form>
</section>

<section class="panel">
<h2>Users</h2>
<table><thead><tr><th>User</th><th>Access</th><th>Password reset</th><th>2FA</th></tr></thead><tbody>
@foreach($users as $user)
<tr>
<td><strong>{{ $user->name }}</strong><br>{{ $user->email }}<br><span class="muted">Last login: {{ $user->last_login_at?->format('d M Y, h:i A') ?? 'Never' }}</span></td>
<td>
<form class="actions" method="post" action="{{ route('admin.users.update',$user) }}">@csrf
<select name="role">@foreach($roles as $role)<option value="{{ $role }}" @selected($user->role===$role)>{{ str_replace('_',' ',$role) }}</option>@endforeach</select>
<label style="display:inline-flex;align-items:center;gap:5px"><input type="checkbox" name="is_active" value="1" @checked($user->is_active)> Active</label>
<button>Save access</button>
</form>
</td>
<td>
<form class="grid" method="post" action="{{ route('admin.users.password',$user) }}">@csrf
<input type="password" name="password" minlength="12" placeholder="New password" required>
<input type="password" name="password_confirmation" minlength="12" placeholder="Confirm" required>
<button>Reset password</button>
</form>
</td>
<td>
@if($user->hasTwoFactorEnabled())
<strong>Enabled</strong>
@if($currentAdmin?->id !== $user->id)
<form method="post" action="{{ route('admin.users.two-factor.reset',$user) }}" style="margin-top:8px">@csrf<button class="danger">Reset 2FA</button></form>
@else
<br><a href="{{ route('admin.security') }}">Manage mine</a>
@endif
@else
<span class="muted">Not enabled</span>
@endif
</td>
</tr>
@endforeach
</tbody></table>
</section>

<section class="panel">
<h2>Authentication security events</h2>
<table><thead><tr><th>Time</th><th>User</th><th>Event</th><th>Network</th></tr></thead><tbody>
@foreach($authEvents as $event)
<tr><td>{{ $event->created_at->format('d M Y, h:i:s A') }}</td><td>{{ $event->adminUser?->name ?? 'Unknown account' }}</td><td>{{ str_replace('_',' ',$event->event_type) }}</td><td>{{ $event->ip_address ?? '—' }}</td></tr>
@endforeach
</tbody></table>
</section>

<section class="panel">
<h2>Recent audit trail</h2>
<table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Result</th></tr></thead><tbody>
@foreach($auditLogs as $log)
<tr><td>{{ $log->created_at->format('d M Y, h:i:s A') }}</td><td>{{ $log->adminUser?->name ?? 'Former user' }}</td><td>{{ $log->method }} {{ $log->route_name ?? $log->path }}@if($log->subject_type)<br><span class="muted">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</span>@endif</td><td>{{ $log->status_code }}</td></tr>
@endforeach
</tbody></table>
</section>
@endsection
