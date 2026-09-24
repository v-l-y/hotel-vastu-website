@extends('admin.layout')
@section('title','Front Desk')
@section('content')
<h1>Front desk</h1>

<section class="panel">
<h2>Confirmed reservations</h2>
@forelse($reservations as $reservation)
<article>
<strong>{{ $reservation->booking_number }}</strong> · {{ $reservation->check_in_date->format('d M Y') }} → {{ $reservation->check_out_date->format('d M Y') }} · ₹{{ number_format((float)$reservation->total,2) }}
<form method="post" action="{{ route('admin.front-desk.check-in',$reservation) }}">@csrf
<p>Select {{ $reservation->rooms->sum('quantity') }} matching physical room(s):</p>
<div class="actions">
@foreach($rooms as $room)
@php($ready=in_array($room->housekeeping_status,['clean','inspected'],true))
<label style="display:inline-flex;gap:5px"><input type="checkbox" name="room_ids[]" value="{{ $room->id }}" @disabled(!$ready)> {{ $room->number }} ({{ $room->roomType->name }}) · {{ $room->housekeeping_status }}</label>
@endforeach
</div>
<button type="submit">Check in</button>
</form>
<div class="actions"><form method="post" action="{{ route('admin.front-desk.cancel',$reservation) }}">@csrf<button class="danger">Cancel reservation</button></form><form method="post" action="{{ route('admin.front-desk.no-show',$reservation) }}">@csrf<button class="danger">Mark no-show</button></form></div>
</article><hr>
@empty<p>No confirmed reservations.</p>@endforelse
</section>

<section class="panel">
<h2>In-house stays</h2>
@forelse($stays as $stay)
@php($activeAssignments=$stay->rooms->whereNull('released_at'))
<article>
<strong>{{ $stay->reservation->booking_number }}</strong> · Rooms {{ $activeAssignments->pluck('room.number')->join(', ') }} · Checkout {{ $stay->reservation->check_out_date->format('d M Y') }} · Balance ₹{{ number_format((float)$stay->folio->balance,2) }}

<div class="grid">
<form method="post" action="{{ route('admin.front-desk.transfer',$stay) }}">@csrf
<h3>Room transfer</h3>
<label>Current room<select name="from_room_id">@foreach($activeAssignments as $assignment)<option value="{{ $assignment->room_id }}">{{ $assignment->room->number }} · {{ $assignment->room->roomType->name }}</option>@endforeach</select></label>
<label>Target room<select name="to_room_id">@foreach($rooms as $room)@if(in_array($room->housekeeping_status,['clean','inspected'],true))<option value="{{ $room->id }}">{{ $room->number }} · {{ $room->roomType->name }}</option>@endif @endforeach</select></label>
<button>Transfer room</button>
</form>

<form method="post" action="{{ route('admin.front-desk.extend',$stay) }}">@csrf
<h3>Extend stay</h3>
<label>New checkout<input type="date" name="new_checkout" min="{{ $stay->reservation->check_out_date->addDay()->toDateString() }}" required></label>
<button>Check availability & extend</button>
</form>
</div>

<form method="post" action="{{ route('admin.front-desk.check-out',$stay) }}">@csrf<button type="submit">Check out</button></form>
</article><hr>
@empty<p>No in-house stays.</p>@endforelse
</section>

<section class="panel">
<h2>Housekeeping</h2>
<table><thead><tr><th>Room</th><th>Current status</th><th>Update</th></tr></thead><tbody>
@foreach($rooms as $room)
<tr><td>{{ $room->number }} · {{ $room->roomType->name }}</td><td>{{ str_replace('_',' ',$room->housekeeping_status) }}</td><td><form class="actions" method="post" action="{{ route('admin.front-desk.housekeeping',$room) }}">@csrf<select name="housekeeping_status">@foreach(['clean','dirty','inspected','out_of_order'] as $status)<option value="{{ $status }}" @selected($room->housekeeping_status===$status)>{{ str_replace('_',' ',$status) }}</option>@endforeach</select><button>Update</button></form></td></tr>
@endforeach
</tbody></table>
</section>

<section class="panel">
<h2>Record verified payment</h2>
<form class="grid" method="post" action="{{ route('admin.payments.store') }}">@csrf
<label>Target<select name="target_type"><option value="reservation">Reservation</option><option value="folio">Folio</option></select></label>
<label>Target ID<input type="number" min="1" name="target_id" required></label>
<label>Method<select name="method"><option>cash</option><option>upi</option><option>card</option><option>bank_transfer</option></select></label>
<label>Amount<input type="number" step="0.01" min="0.01" name="amount" required></label>
<label>Reference<input name="external_reference"></label>
<div><button>Record payment</button></div>
</form>
</section>
@endsection
