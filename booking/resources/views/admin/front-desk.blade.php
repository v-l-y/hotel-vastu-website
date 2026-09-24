@extends('admin.layout')
@section('title','Front Desk')
@section('content')

@if($showNewBooking)
<div class="toolbar">
<div>
<h1>New booking</h1>
<p class="muted">Create a confirmed desk / walk-in reservation. Inventory and pricing are checked when you confirm.</p>
</div>
<a class="button-link" href="{{ route('admin.front-desk') }}">← Back to Front Desk</a>
</div>

<section class="panel" data-front-desk-booking>
<form class="grid" method="post" action="{{ route('admin.front-desk.create') }}">
@csrf
<label>First name<input name="first_name" value="{{ old('first_name') }}" maxlength="100" required></label>
<label>Last name<input name="last_name" value="{{ old('last_name') }}" maxlength="100"></label>
<label>Phone<input name="phone" value="{{ old('phone') }}" maxlength="30" required></label>
<label>Email<input type="email" name="email" value="{{ old('email') }}" maxlength="190"></label>
<label>Check-in<input type="date" name="check_in" value="{{ old('check_in', today()->toDateString()) }}" min="{{ today()->toDateString() }}" required></label>
<label>Check-out<input type="date" name="check_out" value="{{ old('check_out', today()->addDay()->toDateString()) }}" min="{{ today()->addDay()->toDateString() }}" required></label>
<label>Room type<select name="room_type_id" required><option value="">Choose room type</option>@foreach($roomTypes as $type)<option value="{{ $type->id }}" @selected((string)old('room_type_id')===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></label>
<label>Rate plan<select name="rate_plan_id" required><option value="">Choose rate plan</option>@foreach($ratePlans as $plan)<option value="{{ $plan->id }}" @selected((string)old('rate_plan_id')===(string)$plan->id)>{{ $plan->name }}</option>@endforeach</select></label>
<label>Rooms<input type="number" name="rooms" min="1" max="10" value="{{ old('rooms',1) }}" required></label>
<label>Adults<input type="number" name="adults" min="1" max="30" value="{{ old('adults',1) }}" required></label>
<label>Children<input type="number" name="children" min="0" max="30" value="{{ old('children',0) }}" required></label>
<label class="full">Special request<textarea name="special_request" rows="3" maxlength="2000">{{ old('special_request') }}</textarea></label>
<div class="full actions">
<a class="button-link" href="{{ route('admin.front-desk') }}">Cancel</a>
<button type="submit">Check availability & confirm booking</button>
</div>
</form>
</section>
@else

<div class="toolbar">
<div>
<h1>Front desk</h1>
<p class="muted">Today’s arrivals, in-house stays, room readiness and reservation actions.</p>
</div>
<a class="button-link primary" href="{{ route('admin.front-desk',['new'=>1]) }}">+ New booking</a>
</div>

<form class="search-bar" method="get" action="{{ route('admin.front-desk') }}">
<input type="hidden" name="tab" value="{{ $tab }}">
<input name="q" value="{{ $search }}" placeholder="Search booking, guest, phone or email">
<button type="submit">Search</button>
@if($search !== '')<a class="button-link" href="{{ route('admin.front-desk',['tab'=>$tab]) }}">Clear</a>@endif
</form>

<nav class="tabs" aria-label="Front desk sections">
<a class="{{ $tab==='overview' ? 'active' : '' }}" href="{{ route('admin.front-desk',['tab'=>'overview']) }}">Overview</a>
<a class="{{ $tab==='arrivals' ? 'active' : '' }}" href="{{ route('admin.front-desk',['tab'=>'arrivals']) }}">Arrivals</a>
<a class="{{ $tab==='in-house' ? 'active' : '' }}" href="{{ route('admin.front-desk',['tab'=>'in-house']) }}">In-house</a>
<a class="{{ $tab==='reservations' ? 'active' : '' }}" href="{{ route('admin.front-desk',['tab'=>'reservations']) }}">Reservations</a>
<a class="{{ $tab==='housekeeping' ? 'active' : '' }}" href="{{ route('admin.front-desk',['tab'=>'housekeeping']) }}">Housekeeping</a>
</nav>

@if($tab === 'overview')
<div class="metric-grid">
<div class="metric-card"><span class="muted">Arrivals today</span><strong>{{ $arrivalCountToday }}</strong></div>
<div class="metric-card"><span class="muted">In-house stays</span><strong>{{ $stays->count() }}</strong></div>
<div class="metric-card"><span class="muted">Departures today</span><strong>{{ $departureCountToday }}</strong></div>
<div class="metric-card"><span class="muted">Rooms ready</span><strong>{{ $readyRoomCount }}</strong></div>
</div>

<section class="panel">
<div class="toolbar">
<div><h2>Today’s arrivals</h2><p class="muted">Guests due to arrive today.</p></div>
<a class="button-link" href="{{ route('admin.front-desk',['tab'=>'arrivals']) }}">Open arrivals</a>
</div>
@forelse($arrivalsToday as $reservation)
@php($guest=optional($reservation->guestLinks->first())->guest)
@php($line=$reservation->rooms->first())
<div class="compact-row">
<div>
<strong>{{ $reservation->booking_number }}</strong>
@if($guest)<br>{{ trim(($guest->first_name ?? '').' '.($guest->last_name ?? '')) }} · {{ $guest->phone }}@endif
<br><span class="muted">{{ $line?->roomType?->name ?? 'Room' }} · {{ $reservation->rooms->sum('quantity') }} room(s) · ₹{{ number_format((float)$reservation->total,2) }}</span>
</div>
<a class="button-link" href="{{ route('admin.front-desk',['tab'=>'arrivals']).'#reservation-'.$reservation->id }}">Open</a>
</div>
@empty
<p>No arrivals due today.</p>
@endforelse
</section>

<section class="panel">
<div class="toolbar">
<div><h2>In-house</h2><p class="muted">Current checked-in stays.</p></div>
<a class="button-link" href="{{ route('admin.front-desk',['tab'=>'in-house']) }}">Open in-house</a>
</div>
@forelse($stays->take(6) as $stay)
@php($guest=optional($stay->reservation->guestLinks->first())->guest)
@php($activeAssignments=$stay->rooms->whereNull('released_at'))
<div class="compact-row">
<div>
<strong>{{ $stay->reservation->booking_number }}</strong>
@if($guest)<br>{{ trim(($guest->first_name ?? '').' '.($guest->last_name ?? '')) }}@endif
<br><span class="muted">Room(s) {{ $activeAssignments->pluck('room.number')->join(', ') }} · Checkout {{ $stay->reservation->check_out_date->format('d M') }} · Balance ₹{{ number_format((float)$stay->folio->balance,2) }}</span>
</div>
<a class="button-link" href="{{ route('admin.front-desk',['tab'=>'in-house']).'#stay-'.$stay->id }}">Open</a>
</div>
@empty
<p>No guests currently checked in.</p>
@endforelse
</section>

<section class="panel">
<h2>Housekeeping snapshot</h2>
<div class="metric-grid">
<div class="metric-card"><span class="muted">Ready & unoccupied</span><strong>{{ $readyRoomCount }}</strong></div>
<div class="metric-card"><span class="muted">Dirty</span><strong>{{ $dirtyRoomCount }}</strong></div>
<div class="metric-card"><span class="muted">Out of order</span><strong>{{ $outOfOrderRoomCount }}</strong></div>
<div class="metric-card"><span class="muted">Total active rooms</span><strong>{{ $rooms->count() }}</strong></div>
</div>
<a class="button-link" href="{{ route('admin.front-desk',['tab'=>'housekeeping']) }}">Open housekeeping</a>
</section>

@if($feedbacks->isNotEmpty())
<section class="panel">
<h2>Recent guest feedback</h2>
@foreach($feedbacks->take(5) as $feedback)
<div class="compact-row"><div><strong>{{ $feedback->reservation->booking_number }}</strong><br>{{ $feedback->overall_rating }}/5 overall@if($feedback->comment) · {{ $feedback->comment }}@endif</div><span class="muted">{{ $feedback->submitted_at->format('d M') }}</span></div>
@endforeach
</section>
@endif

@elseif($tab === 'arrivals')
<section class="panel">
<h2>Today’s arrivals</h2>
<p class="muted">Assign a ready matching physical room, take payment if needed, then check the guest in.</p>
@forelse($arrivalsToday as $reservation)
@include('admin.partials.front-desk-reservation-card',['reservation'=>$reservation])
@empty
<p>No arrivals match the current search.</p>
@endforelse
</section>

@elseif($tab === 'reservations')
<section class="panel">
<h2>Confirmed reservations</h2>
<p class="muted">Future and same-day confirmed reservations. Edit, take payment, cancel or mark no-show from the booking card.</p>
@forelse($reservations as $reservation)
@include('admin.partials.front-desk-reservation-card',['reservation'=>$reservation])
@empty
<p>No confirmed reservations match the current search.</p>
@endforelse
</section>

@elseif($tab === 'in-house')
<section class="panel">
<h2>In-house stays</h2>
<p class="muted">Manage rooms, stay extensions, folio payment and checkout.</p>
@forelse($stays as $stay)
@php($activeAssignments=$stay->rooms->whereNull('released_at'))
@php($stayGuest=optional($stay->reservation->guestLinks->first())->guest)
<article class="stay-card" id="stay-{{ $stay->id }}">
<div class="stay-head">
<div>
<strong>{{ $stay->reservation->booking_number }}</strong>
<div class="stay-meta">
@if($stayGuest)<span>{{ trim(($stayGuest->first_name ?? '').' '.($stayGuest->last_name ?? '')) }}</span>@endif
<span>Room(s) {{ $activeAssignments->pluck('room.number')->join(', ') }}</span>
<span>Checkout {{ $stay->reservation->check_out_date->format('d M Y') }}</span>
<span>Balance ₹{{ number_format((float)$stay->folio->balance,2) }}</span>
</div>
</div>
<div class="actions">
<a class="button-link" href="{{ route('admin.payments',['folio_id'=>$stay->folio->id]) }}">Record payment</a>
<form method="post" action="{{ route('admin.front-desk.check-out',$stay) }}">@csrf<button type="submit">Check out</button></form>
</div>
</div>

<details>
<summary>Manage stay</summary>
<div class="grid" style="margin-top:12px">
<div>
<h3>Room transfer</h3>
@foreach($activeAssignments as $assignment)
@php($transferRooms=$transferRoomsByAssignment[$assignment->id] ?? collect())
<div class="panel">
<strong>Current room {{ $assignment->room->number }} · {{ $assignment->room->roomType->name }}</strong>
@if($transferRooms->isNotEmpty())
<form class="actions" method="post" action="{{ route('admin.front-desk.transfer',$stay) }}">@csrf
<input type="hidden" name="from_room_id" value="{{ $assignment->room_id }}">
<label>Target room<select name="to_room_id">@foreach($transferRooms as $room)<option value="{{ $room->id }}">{{ $room->number }} · {{ $room->roomType->name }}</option>@endforeach</select></label>
<button>Transfer room</button>
</form>
@else
<p class="muted">No eligible ready room of the same type is currently available.</p>
@endif
</div>
@endforeach
</div>
<form method="post" action="{{ route('admin.front-desk.extend',$stay) }}">@csrf
<h3>Extend stay</h3>
<label>New checkout<input type="date" name="new_checkout" min="{{ $stay->reservation->check_out_date->addDay()->toDateString() }}" required></label>
<button>Check availability & extend</button>
</form>
</div>
</details>
</article>
@empty
<p>No in-house stays match the current search.</p>
@endforelse
</section>

@elseif($tab === 'housekeeping')
<section class="panel">
<div class="toolbar">
<div><h2>Housekeeping</h2><p class="muted">Room readiness used by Front Desk check-in.</p></div>
<div class="actions"><span class="status-badge good">{{ $readyRoomCount }} ready</span><span class="status-badge warn">{{ $dirtyRoomCount }} dirty</span></div>
</div>
<table>
<thead><tr><th>Room</th><th>Type</th><th>Current status</th><th>Update</th></tr></thead>
<tbody>
@foreach($rooms as $room)
<tr>
<td><strong>{{ $room->number }}</strong></td>
<td>{{ $room->roomType->name }}</td>
<td><span class="status-badge {{ in_array($room->housekeeping_status,['clean','inspected'],true) ? 'good' : ($room->housekeeping_status==='dirty' ? 'warn' : '') }}">{{ str_replace('_',' ',$room->housekeeping_status) }}</span></td>
<td><form class="actions" method="post" action="{{ route('admin.front-desk.housekeeping',$room) }}">@csrf<select name="housekeeping_status">@foreach(['clean','dirty','inspected','out_of_order'] as $status)<option value="{{ $status }}" @selected($room->housekeeping_status===$status)>{{ str_replace('_',' ',$status) }}</option>@endforeach</select><button>Update</button></form></td>
</tr>
@endforeach
</tbody>
</table>
</section>
@endif

@endif
@endsection
