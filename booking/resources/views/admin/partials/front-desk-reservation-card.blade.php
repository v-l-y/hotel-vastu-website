@php($checkInRooms=$checkInRoomsByReservation[$reservation->id] ?? collect())
@php($checkInWindowOpen=$checkInWindowOpenByReservation[$reservation->id] ?? false)
@php($checkInReady=$checkInReadyByReservation[$reservation->id] ?? false)
@php($reservationGuest=optional($reservation->guestLinks->first())->guest)
@php($line=$reservation->rooms->first())

<article class="reservation-card" id="reservation-{{ $reservation->id }}">
<div class="reservation-head">
<div>
<strong>{{ $reservation->booking_number }}</strong>
@if($reservation->source === 'front_desk')
<span class="status-badge">Desk booking</span>
@endif
<div class="reservation-meta">
@if($reservationGuest)
<span>{{ trim(($reservationGuest->first_name ?? '').' '.($reservationGuest->last_name ?? '')) }}</span>
<span>{{ $reservationGuest->phone }}</span>
@endif
<span>{{ $reservation->check_in_date->format('d M Y') }} → {{ $reservation->check_out_date->format('d M Y') }}</span>
<span>₹{{ number_format((float)$reservation->total,2) }}</span>
<span>{{ str_replace('_',' ',$reservation->payment_status) }}</span>
</div>
</div>
<div class="actions">
<a class="button-link" href="{{ route('admin.payments',['reservation_id'=>$reservation->id]) }}">Record payment</a>
<details>
<summary class="button-link">Edit booking</summary>
<form class="grid panel" method="post" action="{{ route('admin.front-desk.modify',$reservation) }}">
@csrf
<label>Check-in<input type="date" name="check_in" value="{{ $reservation->check_in_date->toDateString() }}" required></label>
<label>Check-out<input type="date" name="check_out" value="{{ $reservation->check_out_date->toDateString() }}" required></label>
<label>Room type<select name="room_type_id">@foreach($roomTypes as $type)<option value="{{ $type->id }}" @selected($line?->room_type_id===$type->id)>{{ $type->name }}</option>@endforeach</select></label>
<label>Rate plan<select name="rate_plan_id">@foreach($ratePlans as $plan)<option value="{{ $plan->id }}" @selected($line?->rate_plan_id===$plan->id)>{{ $plan->name }}</option>@endforeach</select></label>
<label>Rooms<input type="number" min="1" max="10" name="rooms" value="{{ $line?->quantity ?? 1 }}" required></label>
<label>Adults<input type="number" min="1" max="30" name="adults" value="{{ $reservation->adults }}" required></label>
<label>Children<input type="number" min="0" max="30" name="children" value="{{ $reservation->children }}" required></label>
<div class="full"><button>Check availability & save</button></div>
</form>
</details>
</div>
</div>

@if($checkInWindowOpen)
@if($checkInReady)
<div class="panel">
<span class="status-badge good">Ready for check-in</span>
<form method="post" action="{{ route('admin.front-desk.check-in',$reservation) }}">
@csrf
<p>Select {{ $reservation->rooms->sum('quantity') }} matching physical room(s):</p>
<div class="actions">
@foreach($checkInRooms as $room)
<label style="display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="room_ids[]" value="{{ $room->id }}"> {{ $room->number }} · {{ $room->roomType->name }} · {{ $room->housekeeping_status }}</label>
@endforeach
</div>
<button type="submit">Check in</button>
</form>
</div>
@else
<p><span class="status-badge warn">No ready matching room available</span></p>
@endif
@else
<p class="muted">Check-in becomes available during the reserved stay window after pricing is finalized.</p>
@endif

<details>
<summary>More actions</summary>
<div class="actions" style="margin-top:10px">
<form method="post" action="{{ route('admin.front-desk.cancel',$reservation) }}">@csrf<button class="danger">Cancel reservation</button></form>
@if(!today()->lt($reservation->check_in_date))
<form method="post" action="{{ route('admin.front-desk.no-show',$reservation) }}">@csrf<button class="danger">Mark no-show</button></form>
@endif
</div>
</details>
</article>
