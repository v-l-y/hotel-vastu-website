@extends('admin.layout')
@section('title','Users & Audit')
@section('content')
@php($currentAdmin=request()->attributes->get('admin_user'))
<section class="page-hero">
<div><h1>Users & Audit</h1><p>Create staff accounts, control role access, reset credentials and review authentication/audit activity.</p></div>
<span class="status-badge">{{ $users->count() }} admin user(s)</span>
</section>

<section class="panel">
<div class="section-head"><div><h2>Create admin user</h2><p class="muted">Passwords require 12+ characters with upper/lowercase letters, a number and a symbol.</p></div></div>
<form class="grid" method="post" action="{{ route('admin.users.store') }}">@csrf
<label>Name<input name="name" value="{{ old('name') }}" required></label>
<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
<label>Role<select name="role">@foreach($roles as $role)<option value="{{ $role }}" @selected(old('role')===$role)>{{ ucfirst(str_replace('_',' ',$role)) }}</option>@endforeach</select></label>
<label>Password<input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
<label>Confirm password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
<div><button>Create user</button></div>
</form>
</section>

<section class="panel">
<div class="section-head"><div><h2>Admin users</h2><p class="muted">Role or active-status changes revoke the user’s older sessions.</p></div></div>
@if($users->isEmpty())
<div class="empty-state">No admin users found.</div>
@else
<div class="table-wrap"><table>
<thead><tr><th>User</th><th>Access</th><th>Password reset</th><th>2FA</th></tr></thead>
<tbody>
@foreach($users as $user)
<tr>
<td><strong>{{ $user->name }}</strong><br>{{ $user->email }}<br><span class="muted">Last login: {{ $user->last_login_at?->format('d M Y, h:i A') ?? 'Never' }}</span></td>
<td>
<form class="actions" method="post" action="{{ route('admin.users.update',$user) }}">@csrf
<select name="role">@foreach($roles as $role)<option value="{{ $role }}" @selected($user->role===$role)>{{ ucfirst(str_replace('_',' ',$role)) }}</option>@endforeach</select>
<label style="display:inline-flex;align-items:center;gap:6px"><input type="checkbox" name="is_active" value="1" @checked($user->is_active)> Active</label>
<button>Save access</button>
</form>
</td>
<td>
<form class="actions" method="post" action="{{ route('admin.users.password',$user) }}">@csrf
<input type="password" name="password" minlength="12" placeholder="New password" autocomplete="new-password" required>
<input type="password" name="password_confirmation" minlength="12" placeholder="Confirm password" autocomplete="new-password" required>
<button>Reset password</button>
</form>
</td>
<td>
@if($user->hasTwoFactorEnabled())
<span class="status-badge good">Enabled</span>
@if($currentAdmin?->id !== $user->id)
<form method="post" action="{{ route('admin.users.two-factor.reset',$user) }}" style="margin-top:8px">@csrf<button class="danger">Reset 2FA</button></form>
@else
<div style="margin-top:8px"><a class="button-link" href="{{ route('admin.security') }}">Manage mine</a></div>
@endif
@else
<span class="status-badge warn">Not enabled</span>
@endif
</td>
</tr>
@endforeach
</tbody>
</table></div>
@endif
</section>

<div class="grid">
<section class="panel">
<div class="section-head"><div><h2>Authentication events</h2><p class="muted">Latest sign-in and security events.</p></div><span class="status-badge">{{ $authEvents->count() }} shown</span></div>
@if($authEvents->isEmpty())
<div class="empty-state">No authentication events recorded.</div>
@else
<div class="table-wrap"><table><thead><tr><th>Time</th><th>User</th><th>Event</th><th>Network</th></tr></thead><tbody>
@foreach($authEvents as $event)
<tr><td>{{ $event->created_at->format('d M Y, h:i:s A') }}</td><td>{{ $event->adminUser?->name ?? 'Unknown account' }}</td><td>{{ ucfirst(str_replace('_',' ',$event->event_type)) }}</td><td>{{ $event->ip_address ?? '—' }}</td></tr>
@endforeach
</tbody></table></div>
@endif
</section>

<section class="panel">
<div class="section-head"><div><h2>Recent audit trail</h2><p class="muted">Latest authenticated admin actions.</p></div><span class="status-badge">{{ $auditLogs->count() }} shown</span></div>
@if($auditLogs->isEmpty())
<div class="empty-state">No audit records available.</div>
@else
<div class="table-wrap"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Result</th></tr></thead><tbody>
@foreach($auditLogs as $log)
<tr><td>{{ $log->created_at->format('d M Y, h:i:s A') }}</td><td>{{ $log->adminUser?->name ?? 'Former user' }}</td><td>{{ $log->method }} {{ $log->route_name ?? $log->path }}@if($log->subject_type)<br><span class="muted">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</span>@endif</td><td><span class="status-badge {{ $log->status_code < 400 ? 'good' : 'warn' }}">{{ $log->status_code }}</span></td></tr>
@endforeach
</tbody></table></div>
@endif
</section>
</div>
@endsection
