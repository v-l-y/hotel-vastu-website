@extends('admin.layout')
@section('title','Users & Audit')
@section('content')
<h1>Admin users & audit</h1>

<section class="panel">
<h2>Create admin user</h2>
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
<table><thead><tr><th>User</th><th>Access</th><th>Password reset</th></tr></thead><tbody>
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
</tr>
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
