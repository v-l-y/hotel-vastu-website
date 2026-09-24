@extends('admin.layout')
@section('title','Front Desk')

@push('styles')
<style>
.front-desk-shell{display:grid;gap:18px}
.front-desk-hero{background:linear-gradient(135deg,#241b16 0%,#3a2a21 100%);color:#fff;border-radius:20px;padding:22px;display:flex;gap:18px;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;box-shadow:0 12px 30px rgba(35,30,26,.12)}
.front-desk-hero h1{margin:0 0 6px;font-size:clamp(1.7rem,3vw,2.35rem)}
.front-desk-hero p{margin:0;color:#e8ddd4;max-width:680px}
.front-desk-hero .button-link{background:#fff;color:#2b211b;border-color:#fff}
.front-desk-command{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center}
.front-desk-command .search-bar{max-width:none}
.front-desk-command .search-bar input{background:#fff}
.front-desk-tabs{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin:0}
.front-desk-tabs a{min-height:46px;border-radius:12px;text-align:center}
.front-desk-tabs a.active{box-shadow:0 7px 18px rgba(35,30,26,.12)}
.front-desk-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.front-desk-kpi{background:#fff;border:1px solid #e2dbd4;border-radius:16px;padding:16px;position:relative;overflow:hidden}
.front-desk-kpi:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:#8e7159}
.front-desk-kpi strong{display:block;font-size:1.9rem;line-height:1;margin-top:8px}
.front-desk-kpi small{display:block;color:#766d65;margin-top:7px}
.front-desk-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(300px,.8fr);gap:18px;align-items:start}
.front-desk-stack{display:grid;gap:18px}
.front-desk-section{background:#fff;border:1px solid #e1dad3;border-radius:18px;padding:18px}
.front-desk-section-head{display:flex;gap:12px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px}
.front-desk-section-head h2{margin:0 0 4px}
.front-desk-section-head p{margin:0}
.front-desk-list{display:grid;gap:10px}
.front-desk-list-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:14px;border:1px solid #ece5df;border-radius:14px;background:#fcfaf8}
.front-desk-list-item strong{font-size:1rem}
.front-desk-list-meta{display:flex;gap:7px 12px;flex-wrap:wrap;margin-top:7px;color:#6d655e;font-size:.92rem}
.front-desk-empty{border:1px dashed #d8cfc7;border-radius:14px;padding:22px;text-align:center;background:#fcfaf8;color:#6d655e}
.front-desk-housekeeping-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.front-desk-mini-stat{border:1px solid #e7dfd8;border-radius:14px;padding:14px;background:#fcfaf8}
.front-desk-mini-stat strong{display:block;font-size:1.55rem;margin-top:3px}
.front-desk-room-board{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.front-desk-room-card{border:1px solid #e3dcd5;border-radius:16px;padding:15px;background:#fff;display:grid;gap:12px}
.front-desk-room-card.ready{border-color:#bfd8c3;background:#f8fcf8}
.front-desk-room-card.dirty{border-color:#efd3a6;background:#fffaf2}
.front-desk-room-card.out{border-color:#dec3c3;background:#fff8f8}
.front-desk-room-card.occupied{border-color:#c9c2bb;background:#f4f1ee}
.front-desk-room-card.occupied .front-desk-room-number:after{content:" · Occupied";font-size:.78rem;font-weight:800;color:#6b625b}
.front-desk-room-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
.front-desk-room-number{font-size:1.25rem;font-weight:800}
.front-desk-room-type{color:#6f675f;font-size:.92rem}
.front-desk-room-card form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}
.front-desk-room-card select{min-width:0;width:100%}
.front-desk-stay-grid{display:grid;gap:14px}
.front-desk-stay{border:1px solid #e2dbd4;border-radius:16px;padding:16px;background:#fff}
.front-desk-stay-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:start}
.front-desk-stay-title{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.front-desk-stay-meta{display:flex;gap:8px 14px;flex-wrap:wrap;margin-top:8px;color:#625a53}
.front-desk-room-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;background:#f1ece7;font-weight:700;font-size:.9rem}
.front-desk-manage{margin-top:14px;border-top:1px solid #eee7e1;padding-top:12px}
.front-desk-manage summary{cursor:pointer;font-weight:800;color:#3d2e25}
.front-desk-manage-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:14px;margin-top:12px}
.front-desk-transfer-card{border:1px solid #e9e2dc;border-radius:13px;padding:12px;margin-top:9px;background:#fcfaf8}
.front-desk-booking-shell{max-width:980px;margin:0 auto}
.front-desk-booking-panel{background:#fff;border:1px solid #dfd7d0;border-radius:18px;padding:20px}
.front-desk-form-section{border:0;padding:0;margin:0 0 20px}
.front-desk-form-section legend{font-size:1rem;font-weight:800;margin-bottom:12px}
.front-desk-form-note{padding:12px 14px;border-radius:12px;background:#f4efe9;color:#5d544d;margin-bottom:18px}
.front-desk-actions-bar{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;padding-top:4px}
.front-desk-feedback{display:grid;gap:9px}
.front-desk-feedback-item{padding:12px;border:1px solid #ece5df;border-radius:12px;background:#fcfaf8}
.front-desk-feedback-item p{margin:5px 0 0}
.front-desk-booking-modal{width:min(920px,calc(100vw - 32px));max-height:calc(100vh - 32px);padding:0;border:0;border-radius:20px;background:#fff;color:#231e1a;box-shadow:0 24px 70px rgba(20,14,10,.28);overflow:hidden}
.front-desk-booking-modal::backdrop{background:rgba(26,20,16,.62);backdrop-filter:blur(2px)}
.front-desk-modal-shell{display:grid;grid-template-rows:auto minmax(0,1fr);max-height:calc(100vh - 32px)}
.front-desk-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:20px 22px;border-bottom:1px solid #e8e0da;background:#fcfaf8}
.front-desk-modal-head h2{margin:0 0 5px;font-size:1.45rem}
.front-desk-modal-head p{margin:0;color:#6f675f}
.front-desk-modal-close{width:42px;height:42px;min-height:42px;padding:0;border-radius:999px;background:#eee8e2;color:#2b211b;font-size:1.35rem;line-height:1}
.front-desk-modal-body{overflow:auto;padding:20px 22px}
.front-desk-modal-body .front-desk-form-note{margin-top:0}
.front-desk-form-section{border:0;padding:0;margin:0 0 22px}
.front-desk-form-section legend{font-size:1rem;font-weight:800;margin-bottom:12px}
.front-desk-field-title{display:flex;justify-content:space-between;gap:8px;align-items:baseline}
.front-desk-field-title small{font-weight:500;color:#81776e}
.front-desk-required{color:#7d1c1c;font-weight:800}
.front-desk-field-help{font-size:.82rem;color:#776e66;font-weight:500;margin-top:-2px}
.front-desk-modal-actions{position:sticky;bottom:0;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;padding:14px 0 0;margin-top:4px;background:linear-gradient(to bottom,rgba(255,255,255,0),#fff 18px)}
.front-desk-modal-actions-inner{width:100%;display:flex;justify-content:flex-end;gap:10px;padding-top:14px;border-top:1px solid #ece4de}

@media(max-width:900px){
.front-desk-layout{grid-template-columns:1fr}
.front-desk-room-board{grid-template-columns:repeat(2,minmax(0,1fr))}
.front-desk-command{grid-template-columns:1fr}
.front-desk-tabs{grid-template-columns:repeat(3,minmax(0,1fr))}
}
@media(max-width:640px){
.front-desk-hero{padding:18px;border-radius:16px}
.front-desk-tabs{grid-template-columns:1fr 1fr}
.front-desk-kpis,.front-desk-room-board,.front-desk-housekeeping-summary,.front-desk-manage-grid{grid-template-columns:1fr}
.front-desk-list-item,.front-desk-stay-head{grid-template-columns:1fr}
.front-desk-list-item .button-link{width:100%;box-sizing:border-box}
.front-desk-room-card form{grid-template-columns:1fr}
.front-desk-actions-bar>*{flex:1 1 100%}
.front-desk-actions-bar .button-link,.front-desk-actions-bar button{width:100%;box-sizing:border-box}
}
</style>
@endpush

@section('content')
<div class="front-desk-shell">
<section class="front-desk-hero">
<div>
<h1>Front desk</h1>
<p>Run arrivals, in-house stays, reservations and room readiness from one operational workspace.</p>
</div>
<button type="button" class="button-link" data-open-booking-modal>+ New booking</button>
</section>

<div class="front-desk-command">
<form class="search-bar" method="get" action="{{ route('admin.front-desk') }}">
<input type="hidden" name="tab" value="{{ $tab }}">
<input name="q" value="{{ $search }}" placeholder="Search booking, guest, phone or email" aria-label="Search front desk">
<button type="submit">Search</button>
@if($search !== '')
<a class="button-link" href="{{ route('admin.front-desk',['tab'=>$tab]) }}">Clear</a>
@endif
</form>

<div class="actions">
<span class="status-badge good">{{ $readyRoomCount }} rooms ready</span>
@if($dirtyRoomCount > 0)<span class="status-badge warn">{{ $dirtyRoomCount }} dirty</span>@endif
</div>
</div>

<nav class="tabs front-desk-tabs" aria-label="Front desk sections">
<a class="{{ $tab==='overview' ? 'active' : '' }}" href="{{ route('admin.front-desk',['tab'=>'overview']) }}">Overview</a>
<a class="{{ $tab==='arrivals' ? 'active' : '' }}" href="{{ route('admin.front-desk',['tab'=>'arrivals']) }}">Arrivals</a>
<a class="{{ $tab==='in-house' ? 'active' : '' }}" href="{{ route('admin.front-desk',['tab'=>'in-house']) }}">In-house</a>
<a class="{{ $tab==='reservations' ? 'active' : '' }}" href="{{ route('admin.front-desk',['tab'=>'reservations']) }}">Reservations</a>
<a class="{{ $tab==='housekeeping' ? 'active' : '' }}" href="{{ route('admin.front-desk',['tab'=>'housekeeping']) }}">Housekeeping</a>
</nav>

@if($tab === 'overview')
<div class="front-desk-kpis">
<div class="front-desk-kpi"><span class="muted">Arrivals today</span><strong>{{ $arrivalCountToday }}</strong><small>Guests expected today</small></div>
<div class="front-desk-kpi"><span class="muted">In-house stays</span><strong>{{ $inHouseCount }}</strong><small>Currently checked in</small></div>
<div class="front-desk-kpi"><span class="muted">Departures today</span><strong>{{ $departureCountToday }}</strong><small>Expected checkouts</small></div>
<div class="front-desk-kpi"><span class="muted">Rooms ready</span><strong>{{ $readyRoomCount }}</strong><small>Clean / inspected and free</small></div>
</div>

<div class="front-desk-layout">
<div class="front-desk-stack">
<section class="front-desk-section">
<div class="front-desk-section-head">
<div><h2>Today’s arrivals</h2><p class="muted">Guests due to arrive today.</p></div>
<a class="button-link" href="{{ route('admin.front-desk',['tab'=>'arrivals']) }}">Open arrivals</a>
</div>
<div class="front-desk-list">
@forelse($arrivalsToday as $reservation)
@php($guest=optional($reservation->guestLinks->first())->guest)
@php($line=$reservation->rooms->first())
<div class="front-desk-list-item">
<div>
<strong>{{ $reservation->booking_number }}</strong>
@if($guest)<div class="front-desk-list-meta"><span>{{ trim(($guest->first_name ?? '').' '.($guest->last_name ?? '')) }}</span><span>{{ $guest->phone }}</span></div>@endif
<div class="front-desk-list-meta"><span>{{ $line?->roomType?->name ?? 'Room' }}</span><span>{{ $reservation->rooms->sum('quantity') }} room(s)</span><span>₹{{ number_format((float)$reservation->total,2) }}</span></div>
</div>
<a class="button-link" href="{{ route('admin.front-desk',['tab'=>'arrivals']).'#reservation-'.$reservation->id }}">Open</a>
</div>
@empty
<div class="front-desk-empty">No arrivals due today.</div>
@endforelse
</div>
</section>

<section class="front-desk-section">
<div class="front-desk-section-head">
<div><h2>In-house</h2><p class="muted">Current checked-in stays and balances.</p></div>
<a class="button-link" href="{{ route('admin.front-desk',['tab'=>'in-house']) }}">Open in-house</a>
</div>
<div class="front-desk-list">
@forelse($stays->take(6) as $stay)
@php($guest=optional($stay->reservation->guestLinks->first())->guest)
@php($activeAssignments=$stay->rooms->whereNull('released_at'))
<div class="front-desk-list-item">
<div>
<div class="front-desk-stay-title"><strong>{{ $stay->reservation->booking_number }}</strong><span class="status-badge good">Occupied</span></div>
@if($guest)<div class="front-desk-list-meta"><span>{{ trim(($guest->first_name ?? '').' '.($guest->last_name ?? '')) }}</span></div>@endif
<div class="front-desk-list-meta"><span>Room(s) {{ $activeAssignments->pluck('room.number')->join(', ') }}</span><span>Checkout {{ $stay->reservation->check_out_date->format('d M') }}</span><span>Balance ₹{{ number_format((float)$stay->folio->balance,2) }}</span></div>
</div>
<a class="button-link" href="{{ route('admin.front-desk',['tab'=>'in-house']).'#stay-'.$stay->id }}">Open</a>
</div>
@empty
<div class="front-desk-empty">No guests currently checked in.</div>
@endforelse
</div>
</section>
</div>

<div class="front-desk-stack">
<section class="front-desk-section">
<div class="front-desk-section-head"><div><h2>Housekeeping</h2><p class="muted">Room readiness snapshot.</p></div></div>
<div class="front-desk-housekeeping-summary">
<div class="front-desk-mini-stat"><span class="muted">Ready & unoccupied</span><strong>{{ $readyRoomCount }}</strong></div>
<div class="front-desk-mini-stat"><span class="muted">Dirty</span><strong>{{ $dirtyRoomCount }}</strong></div>
<div class="front-desk-mini-stat"><span class="muted">Out of order</span><strong>{{ $outOfOrderRoomCount }}</strong></div>
<div class="front-desk-mini-stat"><span class="muted">Total active rooms</span><strong>{{ $rooms->count() }}</strong></div>
</div>
<div style="margin-top:12px"><a class="button-link" href="{{ route('admin.front-desk',['tab'=>'housekeeping']) }}">Open housekeeping</a></div>
</section>

@if($feedbacks->isNotEmpty())
<section class="front-desk-section">
<div class="front-desk-section-head"><div><h2>Recent guest feedback</h2><p class="muted">Latest submitted ratings.</p></div></div>
<div class="front-desk-feedback">
@foreach($feedbacks->take(5) as $feedback)
<div class="front-desk-feedback-item">
<strong>{{ $feedback->reservation->booking_number }} · {{ $feedback->overall_rating }}/5</strong>
@if($feedback->comment)<p>{{ $feedback->comment }}</p>@endif
<div class="muted">{{ $feedback->submitted_at->format('d M') }}</div>
</div>
@endforeach
</div>
</section>
@endif
</div>
</div>

@elseif($tab === 'arrivals')
<section class="front-desk-section">
<div class="front-desk-section-head">
<div><h2>Today’s arrivals</h2><p class="muted">Assign a ready matching physical room, take payment if needed, then check the guest in.</p></div>
<span class="status-badge">{{ $arrivalsToday->count() }} arrival(s)</span>
</div>
@forelse($arrivalsToday as $reservation)
@include('admin.partials.front-desk-reservation-card',['reservation'=>$reservation])
@empty
<div class="front-desk-empty">No arrivals match the current search.</div>
@endforelse
</section>

@elseif($tab === 'reservations')
<section class="front-desk-section">
<div class="front-desk-section-head">
<div><h2>Confirmed reservations</h2><p class="muted">Future and same-day confirmed reservations. Edit, take payment, cancel or mark no-show from the booking card.</p></div>
<span class="status-badge">{{ $reservations->count() }} booking(s)</span>
</div>
@forelse($reservations as $reservation)
@include('admin.partials.front-desk-reservation-card',['reservation'=>$reservation])
@empty
<div class="front-desk-empty">No confirmed reservations match the current search.</div>
@endforelse
</section>

@elseif($tab === 'in-house')
<section class="front-desk-section">
<div class="front-desk-section-head">
<div><h2>In-house stays</h2><p class="muted">Manage occupied rooms, stay extensions, folio payment and checkout.</p></div>
<span class="status-badge good">{{ $stays->count() }} occupied stay(s)</span>
</div>
<div class="front-desk-stay-grid">
@forelse($stays as $stay)
@php($activeAssignments=$stay->rooms->whereNull('released_at'))
@php($stayGuest=optional($stay->reservation->guestLinks->first())->guest)
<article class="front-desk-stay stay-card" id="stay-{{ $stay->id }}">
<div class="front-desk-stay-head">
<div>
<div class="front-desk-stay-title"><strong>{{ $stay->reservation->booking_number }}</strong><span class="status-badge good">Occupied</span></div>
<div class="front-desk-stay-meta">
@if($stayGuest)<span>{{ trim(($stayGuest->first_name ?? '').' '.($stayGuest->last_name ?? '')) }}</span>@endif
@foreach($activeAssignments as $assignment)<span class="front-desk-room-chip">Room {{ $assignment->room->number }}</span>@endforeach
<span>Checkout {{ $stay->reservation->check_out_date->format('d M Y') }}</span>
<span>Balance ₹{{ number_format((float)$stay->folio->balance,2) }}</span>
</div>
</div>

<div class="actions">
@if(abs((float)$stay->folio->balance) > 0.009)
<a class="button-link primary" href="{{ route('admin.payments',['folio_id'=>$stay->folio->id]) }}">Settle ₹{{ number_format((float)$stay->folio->balance,2) }}</a>
<span class="status-badge warn">Settle balance before checkout</span>
@else
<a class="button-link" href="{{ route('admin.payments',['folio_id'=>$stay->folio->id]) }}">Payments</a>
<form method="post" action="{{ route('admin.front-desk.check-out',$stay) }}">@csrf<button type="submit">Check out</button></form>
@endif
</div>
</div>

<details class="front-desk-manage">
<summary>Manage stay</summary>
<div class="front-desk-manage-grid">
<div>
<h3>Room transfer</h3>
@foreach($activeAssignments as $assignment)
@php($transferRooms=$transferRoomsByAssignment[$assignment->id] ?? collect())
<div class="front-desk-transfer-card">
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
<div style="margin-top:10px"><button>Check availability & extend</button></div>
</form>
</div>
</details>
</article>
@empty
<div class="front-desk-empty">No in-house stays match the current search.</div>
@endforelse
</div>
</section>

@elseif($tab === 'housekeeping')
<section class="front-desk-section">
<div class="front-desk-section-head">
<div><h2>Housekeeping</h2><p class="muted">Checkout rooms automatically become dirty. Mark them clean or inspected before the next check-in.</p></div>
<div class="actions"><span class="status-badge good">{{ $readyRoomCount }} ready</span><span class="status-badge warn">{{ $dirtyRoomCount }} dirty</span></div>
</div>

<div class="front-desk-room-board">
@foreach($rooms as $room)
@php($roomOccupied=in_array((int)$room->id,$occupiedRoomIds,true))
@php($roomClass=$roomOccupied ? 'occupied' : (in_array($room->housekeeping_status,['clean','inspected'],true) ? 'ready' : ($room->housekeeping_status==='dirty' ? 'dirty' : ($room->housekeeping_status==='out_of_order' ? 'out' : ''))))
<article class="front-desk-room-card {{ $roomClass }}">
<div class="front-desk-room-top">
<div><div class="front-desk-room-number">Room {{ $room->number }}</div><div class="front-desk-room-type">{{ $room->roomType->name }}</div></div>
@if($roomOccupied)
<span class="status-badge">Occupied</span>
@else
<span class="status-badge {{ in_array($room->housekeeping_status,['clean','inspected'],true) ? 'good' : ($room->housekeeping_status==='dirty' ? 'warn' : '') }}">{{ str_replace('_',' ',$room->housekeeping_status) }}</span>
@endif
</div>
@if($roomOccupied)
<div class="front-desk-field-help">Housekeeping status is locked while a guest is checked in. Checkout or transfer the room first.</div>
@else
<form method="post" action="{{ route('admin.front-desk.housekeeping',$room) }}">@csrf
<select name="housekeeping_status" aria-label="Housekeeping status for room {{ $room->number }}">@foreach(['clean','dirty','inspected','out_of_order'] as $status)<option value="{{ $status }}" @selected($room->housekeeping_status===$status)>{{ str_replace('_',' ',$status) }}</option>@endforeach</select>
<button>Update</button>
</form>
@endif
</article>
@endforeach
</div>
</section>
@endif
</div>

<dialog id="new-booking-modal" class="front-desk-booking-modal" aria-labelledby="new-booking-title">
<div class="front-desk-modal-shell">
<div class="front-desk-modal-head">
<div>
<h2 id="new-booking-title">New booking</h2>
<p>Create a confirmed desk / walk-in reservation.</p>
</div>
<button type="button" class="front-desk-modal-close" data-close-booking-modal aria-label="Close new booking">×</button>
</div>

<div class="front-desk-modal-body">
<div class="front-desk-form-note">
<strong>Before you confirm:</strong> availability and pricing are checked again when the booking is saved. A physical room number is assigned later at check-in.
</div>

<form method="post" action="{{ route('admin.front-desk.create') }}" data-front-desk-booking>
@csrf
<input type="hidden" name="_booking_modal" value="1">

<fieldset class="front-desk-form-section">
<legend>Guest details</legend>
<div class="grid">
<label>
<span class="front-desk-field-title"><span>First name <span class="front-desk-required">*</span></span></span>
<input name="first_name" value="{{ old('first_name') }}" maxlength="100" autocomplete="given-name" required autofocus>
</label>
<label>
<span class="front-desk-field-title"><span>Last name</span><small>Optional</small></span>
<input name="last_name" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name">
</label>
<label>
<span class="front-desk-field-title"><span>Phone <span class="front-desk-required">*</span></span></span>
<input type="tel" name="phone" value="{{ old('phone') }}" maxlength="30" inputmode="tel" autocomplete="tel" placeholder="+91 98765 43210" required>
</label>
<label>
<span class="front-desk-field-title"><span>Email</span><small>Optional</small></span>
<input type="email" name="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" placeholder="guest@example.com">
<span class="front-desk-field-help">Used for booking confirmation when provided.</span>
</label>
</div>
</fieldset>

<fieldset class="front-desk-form-section">
<legend>Stay details</legend>
<div class="grid">
<label>
<span class="front-desk-field-title"><span>Check-in <span class="front-desk-required">*</span></span></span>
<input type="date" name="check_in" value="{{ old('check_in', today()->toDateString()) }}" min="{{ today()->toDateString() }}" required>
</label>
<label>
<span class="front-desk-field-title"><span>Check-out <span class="front-desk-required">*</span></span></span>
<input type="date" name="check_out" value="{{ old('check_out', today()->addDay()->toDateString()) }}" min="{{ today()->addDay()->toDateString() }}" required>
</label>
<label>
<span class="front-desk-field-title"><span>Rooms <span class="front-desk-required">*</span></span></span>
<input type="number" name="rooms" min="1" max="10" value="{{ old('rooms',1) }}" inputmode="numeric" required>
</label>
<label>
<span class="front-desk-field-title"><span>Adults <span class="front-desk-required">*</span></span></span>
<input type="number" name="adults" min="1" max="30" value="{{ old('adults',1) }}" inputmode="numeric" required>
</label>
<label>
<span class="front-desk-field-title"><span>Children <span class="front-desk-required">*</span></span></span>
<input type="number" name="children" min="0" max="30" value="{{ old('children',0) }}" inputmode="numeric" required>
</label>
</div>
</fieldset>

<fieldset class="front-desk-form-section">
<legend>Room & rate</legend>
<div class="grid">
<label>
<span class="front-desk-field-title"><span>Room type <span class="front-desk-required">*</span></span></span>
<select name="room_type_id" required>
<option value="">Choose room type</option>
@foreach($roomTypes as $type)
<option value="{{ $type->id }}" @selected((string)old('room_type_id')===(string)$type->id)>{{ $type->name }}</option>
@endforeach
</select>
</label>
<label>
<span class="front-desk-field-title"><span>Rate plan <span class="front-desk-required">*</span></span></span>
<select name="rate_plan_id" required>
<option value="">Choose rate plan</option>
@foreach($ratePlans as $plan)
<option value="{{ $plan->id }}" @selected((string)old('rate_plan_id')===(string)$plan->id)>{{ $plan->name }}</option>
@endforeach
</select>
</label>
<label>
<span class="front-desk-field-title"><span>Promo code</span><small>Optional</small></span>
<input name="promo_code" value="{{ old('promo_code') }}" maxlength="40" autocomplete="off" placeholder="Enter active promo code">
<span class="front-desk-field-help">Validated against active dates, minimum subtotal and usage limit.</span>
</label>
</div>
</fieldset>

<label>
<span class="front-desk-field-title"><span>Special request</span><small>Optional</small></span>
<textarea name="special_request" rows="3" maxlength="2000" placeholder="Late arrival, accessibility need, food preference, or other guest note">{{ old('special_request') }}</textarea>
</label>

<div class="front-desk-modal-actions">
<div class="front-desk-modal-actions-inner">
<button type="button" class="button-link" data-close-booking-modal>Cancel</button>
<button type="submit">Check availability & confirm booking</button>
</div>
</div>
</form>
</div>
</div>
</dialog>

<script>
(() => {
    const dialog = document.getElementById('new-booking-modal');
    if (!dialog) return;

    const open = () => {
        if (!dialog.open) dialog.showModal();
    };

    const cleanNewBookingUrl = () => {
        const url = new URL(window.location.href);
        if (url.searchParams.has('new')) {
            url.searchParams.delete('new');
            history.replaceState({}, '', url.pathname + (url.search ? url.search : '') + url.hash);
        }
    };

    const close = () => {
        if (dialog.open) dialog.close();
    };

    document.querySelectorAll('[data-open-booking-modal]').forEach((button) => {
        button.addEventListener('click', open);
    });

    dialog.querySelectorAll('[data-close-booking-modal]').forEach((button) => {
        button.addEventListener('click', close);
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });

    dialog.addEventListener('close', cleanNewBookingUrl);

    const shouldOpen = @json($showNewBooking || (string) old('_booking_modal') === '1');
    if (shouldOpen) open();
})();
</script>

@endsection
