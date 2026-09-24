@extends('admin.layout')
@section('title','Users & Audit')
@section('content')
@php($currentAdmin=request()->attributes->get('admin_user'))

<section class="page-hero">
<div class="section-title">
@include('admin.partials.icon',['name'=>'users'])
<div>
<h1>Users & Audit</h1>
<p>Create staff accounts, control role access, reset credentials and review authentication/audit activity.</p>
</div>
</div>
<span class="status-badge">{{ $users->total() }} admin user(s)</span>
</section>

<section class="panel">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'add'])
<div><h2>Create admin user</h2><p class="muted">Passwords require 12+ characters with upper/lowercase letters, a number and a symbol.</p></div>
</div>
</div>
<form class="grid" method="post" action="{{ route('admin.users.store') }}">
@csrf
<label>Name<input name="name" value="{{ old('name') }}" required></label>
<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
<label>Role
<select name="role">
@foreach($roles as $role)
<option value="{{ $role }}" @selected(old('role')===$role)>{{ ucfirst(str_replace('_',' ',$role)) }}</option>
@endforeach
</select>
</label>
<label>Password<input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
<label>Confirm password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
<div><button type="submit">Create user</button></div>
</form>
</section>

<section class="panel" id="admin-users">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'users'])
<div><h2>Admin users</h2><p class="muted">Role or active-status changes revoke the user’s older sessions.</p></div>
</div>
<span class="status-badge">{{ $users->total() }} total</span>
</div>

@if($users->count() === 0)
<div class="empty-state">No admin users found.</div>
@else
<div class="table-wrap">
<table>
<thead>
<tr><th>User</th><th>Role & status</th><th>Security</th><th>Actions</th></tr>
</thead>
<tbody>
@foreach($users as $user)
<tr>
<td>
<span class="table-primary">{{ $user->name }}</span>
<span class="table-secondary">{{ $user->email }}</span>
<span class="table-secondary">Last login: {{ $user->last_login_at?->format('d M Y, h:i A') ?? 'Never' }}</span>
</td>
<td>
<form class="table-actions" method="post" action="{{ route('admin.users.update',$user) }}">
@csrf
<select name="role" aria-label="Role for {{ $user->name }}">
@foreach($roles as $role)
<option value="{{ $role }}" @selected($user->role===$role)>{{ ucfirst(str_replace('_',' ',$role)) }}</option>
@endforeach
</select>
<label style="display:inline-flex;align-items:center;gap:6px;font-weight:700">
<input type="checkbox" name="is_active" value="1" @checked($user->is_active)> Active
</label>
<button type="submit">Save access</button>
</form>
</td>
<td>
@if($user->hasTwoFactorEnabled())
<span class="status-badge good">2FA enabled</span>
@else
<span class="status-badge warn">2FA not enabled</span>
@endif
@if($currentAdmin?->id === $user->id)
<span class="table-secondary">This is your account.</span>
@endif
</td>
<td>
<details class="action-menu">
<summary>Account actions</summary>
<form class="grid" method="post" action="{{ route('admin.users.password',$user) }}" style="grid-template-columns:1fr">
@csrf
<label>New password<input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
<label>Confirm password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
<button type="submit">Reset password</button>
</form>
@if($user->hasTwoFactorEnabled())
@if($currentAdmin?->id !== $user->id)
<form method="post" action="{{ route('admin.users.two-factor.reset',$user) }}" style="margin-top:10px">
@csrf
<button class="danger" type="submit">Reset 2FA</button>
</form>
@else
<div style="margin-top:10px"><a class="button-link" href="{{ route('admin.security') }}">Manage my 2FA</a></div>
@endif
@endif
</details>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$users,'label'=>'Admin users pagination'])
@endif
</section>

<section class="panel" id="auth-events">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'security'])
<div><h2>Authentication events</h2><p class="muted">Sign-in, 2FA and account-security events.</p></div>
</div>
<span class="status-badge">{{ $authEvents->total() }} total</span>
</div>

@if($authEvents->count() === 0)
<div class="empty-state">No authentication events recorded.</div>
@else
<div class="table-wrap">
<table>
<thead><tr><th>Time</th><th>User</th><th>Event</th><th>Network</th></tr></thead>
<tbody>
@foreach($authEvents as $event)
<tr>
<td>{{ $event->created_at->format('d M Y, h:i:s A') }}</td>
<td>{{ $event->adminUser?->name ?? 'Unknown account' }}</td>
<td><span class="status-badge">{{ ucfirst(str_replace('_',' ',$event->event_type)) }}</span></td>
<td>{{ $event->ip_address ?? '—' }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$authEvents,'label'=>'Authentication events pagination'])
@endif
</section>

<section class="panel" id="audit-trail">
<div class="section-head">
<div class="section-title">
@include('admin.partials.icon',['name'=>'reports'])
<div><h2>Recent audit trail</h2><p class="muted">Authenticated admin actions with result codes.</p></div>
</div>
<span class="status-badge">{{ $auditLogs->total() }} total</span>
</div>

@if($auditLogs->count() === 0)
<div class="empty-state">No audit records available.</div>
@else
<div class="table-wrap">
<table>
<thead><tr><th>Time</th><th>User</th><th>Action</th><th>Result</th></tr></thead>
<tbody>
@foreach($auditLogs as $log)
<tr>
<td>{{ $log->created_at->format('d M Y, h:i:s A') }}</td>
<td>{{ $log->adminUser?->name ?? 'Former user' }}</td>
<td>
<span class="table-primary">{{ $log->method }} {{ $log->route_name ?? $log->path }}</span>
@if($log->subject_type)
<span class="table-secondary">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</span>
@endif
</td>
<td><span class="status-badge {{ $log->status_code < 400 ? 'good' : 'warn' }}">{{ $log->status_code }}</span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$auditLogs,'label'=>'Audit trail pagination'])
@endif
</section>
@endsection
