@php($checkInRooms=$checkInRoomsByReservation[$reservation->id] ?? collect())
@php($checkInWindowOpen=$checkInWindowOpenByReservation[$reservation->id] ?? false)
@php($checkInReady=$checkInReadyByReservation[$reservation->id] ?? false)
@php($reservationGuest=optional($reservation->guestLinks->first())->guest)
@php($line=$reservation->rooms->first())

<article class="reservation-card front-desk-stay" id="reservation-{{ $reservation->id }}">
<div class="front-desk-stay-head">
<div>
<div class="front-desk-stay-title">
<strong>{{ $reservation->booking_number }}</strong>
@if($reservation->source === 'front_desk')
<span class="status-badge">Desk booking</span>
@endif
@if($checkInWindowOpen && $checkInReady)
<span class="status-badge good">Ready for check-in</span>
@elseif($checkInWindowOpen)
<span class="status-badge warn">Waiting for ready room</span>
@endif
</div>

<div class="front-desk-stay-meta">
@if($reservationGuest)
<span>{{ trim(($reservationGuest->first_name ?? '').' '.($reservationGuest->last_name ?? '')) }}</span>
<span>{{ $reservationGuest->phone }}</span>
@endif
<span>{{ $reservation->check_in_date->format('d M Y') }} → {{ $reservation->check_out_date->format('d M Y') }}</span>
<span>{{ $line?->roomType?->name ?? 'Room' }} · {{ $reservation->rooms->sum('quantity') }} room(s)</span>
<span>₹{{ number_format((float)$reservation->total,2) }}</span>
@if((float)$reservation->discount > 0)<span>Discount −₹{{ number_format((float)$reservation->discount,2) }}</span>@endif
@if($reservation->promotion_code_snapshot)<span>Promo {{ $reservation->promotion_code_snapshot }}</span>@endif
<span>{{ str_replace('_',' ',$reservation->payment_status) }}</span>
</div>
</div>

<div class="actions">
<a class="button-link" href="{{ route('admin.payments',['reservation_id'=>$reservation->id]) }}">Record payment</a>
<details>
<summary class="button-link">Edit booking</summary>
<div class="front-desk-transfer-card">
<form class="grid" method="post" action="{{ route('admin.front-desk.modify',$reservation) }}">
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
</div>
</details>
</div>
</div>

@if($checkInWindowOpen)
@if($checkInReady)
<div class="front-desk-transfer-card">
<form method="post" action="{{ route('admin.front-desk.check-in',$reservation) }}">
@csrf
<div class="front-desk-section-head">
<div>
<strong>Select physical room</strong>
<p class="muted">Choose {{ $reservation->rooms->sum('quantity') }} matching ready room(s) for this stay.</p>
</div>
<button type="submit">Check in</button>
</div>
<div class="actions">
@foreach($checkInRooms as $room)
<label class="front-desk-room-chip"><input type="checkbox" name="room_ids[]" value="{{ $room->id }}"> Room {{ $room->number }} · {{ $room->roomType->name }} · {{ $room->housekeeping_status }}</label>
@endforeach
</div>
</form>
</div>
@else
<div class="front-desk-empty">No ready matching physical room is currently available for check-in.</div>
@endif
@else
<p class="muted">Check-in becomes available during the reserved stay window after pricing is finalized.</p>
@endif

<details class="front-desk-manage">
<summary>More actions</summary>
<div style="margin-top:10px">
@if($reservation->promotion_code_snapshot)
<div class="front-desk-form-note"><strong>Promo {{ $reservation->promotion_code_snapshot }}</strong> applied · saved ₹{{ number_format((float)$reservation->discount,2) }}. Promo snapshots are not replaced by manual discounts.</div>
@else
<div class="front-desk-transfer-card">
<strong>{{ $reservation->discount_source === 'manual' ? 'Update manual discount' : 'Manual discount' }}</strong>
<form class="grid" method="post" action="{{ route('admin.front-desk.discount',$reservation) }}" style="margin-top:10px">
@csrf
<label>Type<select name="discount_type"><option value="fixed" @selected($reservation->discount_type==='fixed')>Fixed amount</option><option value="percent" @selected($reservation->discount_type==='percent')>Percent</option></select></label>
<label>Value<input type="number" step="0.01" min="0.01" name="discount_value" value="{{ $reservation->discount_source === 'manual' ? $reservation->discount_value : '' }}" required><span class="front-desk-field-help">A full-value / 100% discount requires Administrator approval.</span></label>
<label class="full">Reason<input name="discount_reason" maxlength="255" value="{{ $reservation->discount_source === 'manual' ? $reservation->discount_reason : '' }}" placeholder="Manager adjustment / service recovery / negotiated rate" required></label>
<div class="full"><button type="submit">{{ $reservation->discount_source === 'manual' ? 'Update discount' : 'Apply discount' }}</button></div>
</form>
</div>
@endif
<div class="actions" style="margin-top:10px">
<form method="post" action="{{ route('admin.front-desk.cancel',$reservation) }}">@csrf<button class="danger">Cancel reservation</button></form>
@if(!today()->lt($reservation->check_in_date))
<form method="post" action="{{ route('admin.front-desk.no-show',$reservation) }}">@csrf<button class="danger">Mark no-show</button></form>
@endif
</div>
</div>
</details>
</article>
