@extends('admin.layout')
@section('title', ($kitchenOnly ?? false) ? 'Kitchen KOT' : 'Restaurant & KOT')

@push('styles')
<style>
.restaurant-heading{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px}
.restaurant-heading h1{margin:0 0 6px}.restaurant-heading p{margin:0}
.order-context[hidden]{display:none}.order-summary{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:10px}
.menu-toolbar{display:flex;gap:10px;align-items:end;justify-content:space-between;flex-wrap:wrap;margin:10px 0}.menu-toolbar label{min-width:min(100%,360px)}
.kot-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.kot-card{border:1px solid #ddd6cf;border-radius:14px;padding:16px;background:#fff}.kot-card-head{display:flex;gap:12px;justify-content:space-between;align-items:flex-start}.kot-card h3{margin:0}.kot-meta{display:flex;gap:8px 14px;flex-wrap:wrap;margin:8px 0 14px;color:#5f5750}.kot-items{display:grid;gap:8px;margin:12px 0}.kot-item{padding:9px 10px;border-radius:9px;background:#f8f5f1}.kot-note{display:block;margin-top:4px;font-weight:700;color:#7a4a08}.kot-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:14px}.status-badge.accepted{background:#fff2df;color:#7a4a08}.status-badge.preparing{background:#fff2df;color:#7a4a08}.status-badge.ready{background:#e8f5ea;color:#245d2d}.status-badge.served{background:#e8f5ea;color:#245d2d}.status-badge.cancelled{background:#fff0f0;color:#771717}.restaurant-history td{min-width:110px}.restaurant-history td:nth-child(2){min-width:180px}.empty-state{padding:22px;text-align:center;border:1px dashed #cfc5bc;border-radius:12px;color:#6f675f}
@media(max-width:760px){.kot-grid{grid-template-columns:1fr}.restaurant-heading{align-items:stretch}.menu-toolbar label{min-width:100%}}
</style>
@endpush

@section('content')
<div class="restaurant-heading">
<div>
<h1>{{ ($kitchenOnly ?? false) ? 'Kitchen KOT' : 'Restaurant & KOT' }}</h1>
<p class="muted">{{ ($kitchenOnly ?? false) ? 'Oldest active tickets appear first. Update only the kitchen stages shown on each ticket.' : 'Create restaurant orders, follow KOT progress, settle bills, and keep table status in sync.' }}</p>
</div>
<span class="status-badge {{ $activeOrders->isEmpty() ? 'good' : 'warn' }}">{{ $activeOrders->count() }} active</span>
</div>

@unless($kitchenOnly ?? false)
@php($selectedType = old('order_type', 'dine_in'))
@php($menuGroups = $menuItems->groupBy(fn($item) => $item->category?->name ?? 'Menu'))
@php($oldItems = old('items', [['menu_item_id' => '', 'quantity' => 1, 'note' => '']]))
<section class="panel">
<div class="toolbar">
<div><h2 style="margin:0">New order</h2><span class="muted">Only fields relevant to the selected order type are submitted.</span></div>
<strong id="order-estimate">Estimated subtotal ₹0.00</strong>
</div>
<form class="grid" id="restaurant-order-form" method="post" action="{{ route('admin.restaurant.orders.store') }}">
@csrf
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

<label>Order type
<select name="order_type" id="restaurant-order-type">
<option value="dine_in" @selected($selectedType==='dine_in')>Dine-in</option>
<option value="room_service" @selected($selectedType==='room_service')>Room service</option>
<option value="takeaway" @selected($selectedType==='takeaway')>Takeaway</option>
</select>
</label>

<div class="order-context" data-order-context="dine_in">
<label>Restaurant table
<select name="restaurant_table_id" data-context-control data-context-required>
<option value="">Choose available table</option>
@foreach($tables as $table)
<option value="{{ $table->id }}" @selected((string)old('restaurant_table_id')===(string)$table->id) @disabled($table->status!=='available')>{{ $table->name }} · {{ ucfirst($table->status) }}@if($table->capacity) · {{ $table->capacity }} seats@endif</option>
@endforeach
</select>
</label>
</div>

<div class="order-context" data-order-context="room_service">
<label>Guest folio / room
<select name="folio_id" data-context-control data-context-required>
<option value="">Choose in-house guest folio</option>
@foreach($folios as $folio)
@php($folioRooms = $folio->stay?->rooms?->whereNull('released_at')->pluck('room.number')->filter()->implode(', '))
<option value="{{ $folio->id }}" @selected((string)old('folio_id')===(string)$folio->id)>Folio #{{ $folio->id }} · Reservation #{{ $folio->reservation_id }}@if($folioRooms) · Room {{ $folioRooms }}@endif</option>
@endforeach
</select>
</label>
</div>

<div class="order-context" data-order-context="takeaway">
<label>Guest name<input name="guest_name" value="{{ old('guest_name') }}" maxlength="160" data-context-control autocomplete="name"></label>
</div>
<div class="order-context" data-order-context="takeaway">
<label>Phone<input name="guest_phone" value="{{ old('guest_phone') }}" maxlength="30" data-context-control autocomplete="tel"></label>
</div>

<div class="full">
<div class="menu-toolbar">
<div><strong>Items</strong><div class="muted">Special instructions are printed in the KOT view.</div></div>
<label>Find menu item<input type="search" id="menu-search" placeholder="Search by item or category" autocomplete="off"></label>
</div>

<div id="order-items">
@foreach($oldItems as $index => $oldItem)
<div class="item-row" data-item-row>
<label>Menu item
<select name="items[{{ $index }}][menu_item_id]" data-menu-select required>
<option value="">Choose item</option>
@foreach($menuGroups as $categoryName => $items)
<optgroup label="{{ $categoryName }}">
@foreach($items as $item)
<option value="{{ $item->id }}" data-price="{{ number_format((float)$item->price,2,'.','') }}" data-search="{{ strtolower($categoryName.' '.$item->name) }}" @selected((string)($oldItem['menu_item_id'] ?? '')===(string)$item->id)>{{ $item->name }} · ₹{{ number_format((float)$item->price,2) }}{{ $item->is_vegetarian ? ' · Veg' : '' }}</option>
@endforeach
</optgroup>
@endforeach
</select>
</label>
<label>Qty<input type="number" min="1" max="50" name="items[{{ $index }}][quantity]" value="{{ $oldItem['quantity'] ?? 1 }}" data-quantity required></label>
<label>Kitchen note<input name="items[{{ $index }}][note]" value="{{ $oldItem['note'] ?? '' }}" maxlength="500" placeholder="e.g. no onion"></label>
<button type="button" data-remove-item @disabled(count($oldItems)===1)>Remove</button>
</div>
@endforeach
</div>

<button type="button" id="add-order-item">Add another item</button>
<div class="order-summary"><span class="muted">Maximum 30 lines per order.</span></div>
</div>
<div><button type="submit" id="create-kot-button">Create order & KOT</button></div>
</form>

<template id="order-item-template">
<div class="item-row" data-item-row>
<label>Menu item
<select data-field="menu_item_id" data-menu-select required>
<option value="">Choose item</option>
@foreach($menuGroups as $categoryName => $items)
<optgroup label="{{ $categoryName }}">
@foreach($items as $item)
<option value="{{ $item->id }}" data-price="{{ number_format((float)$item->price,2,'.','') }}" data-search="{{ strtolower($categoryName.' '.$item->name) }}">{{ $item->name }} · ₹{{ number_format((float)$item->price,2) }}{{ $item->is_vegetarian ? ' · Veg' : '' }}</option>
@endforeach
</optgroup>
@endforeach
</select>
</label>
<label>Qty<input type="number" min="1" max="50" value="1" data-field="quantity" data-quantity required></label>
<label>Kitchen note<input data-field="note" maxlength="500" placeholder="e.g. no onion"></label>
<button type="button" data-remove-item>Remove</button>
</div>
</template>
</section>

<section class="panel">
<div class="toolbar"><h2 style="margin:0">Restaurant tables</h2><span class="muted">Occupied tables release on cancellation or after a served bill is fully paid.</span></div>
<div class="cards">
@forelse($tables as $table)
<div class="card"><strong>{{ $table->name }}</strong><span class="status-badge {{ $table->status==='available' ? 'good' : 'warn' }}">{{ ucfirst($table->status) }}</span>@if($table->capacity)<br><span class="muted">Capacity {{ $table->capacity }}</span>@endif</div>
@empty
<div class="empty-state full">No active restaurant tables configured.</div>
@endforelse
</div>
</section>
@endunless

<section class="panel">
<div class="toolbar">
<div><h2 style="margin:0">{{ ($kitchenOnly ?? false) ? 'Active KOT queue' : 'Active orders & KOT' }}</h2><span class="muted">Oldest waiting order is shown first.</span></div>
</div>

@if($activeOrders->isEmpty())
<div class="empty-state">No active KOTs.</div>
@else
<div class="kot-grid">
@foreach($activeOrders as $order)
@php($roomNumbers = $order->folio?->stay?->rooms?->whereNull('released_at')->pluck('room.number')->filter()->implode(', '))
@php($destination = match($order->order_type) {
'dine_in' => $order->restaurantTable ? $order->restaurantTable->name : 'Table unavailable',
'room_service' => $roomNumbers ? 'Room '.$roomNumbers : 'Folio #'.($order->folio_id ?? '—'),
'takeaway' => trim((string)$order->guest_name) !== '' ? 'Takeaway · '.$order->guest_name : 'Takeaway counter',
default => ucfirst(str_replace('_',' ',$order->order_type)),
})
<div class="kot-card">
<div class="kot-card-head">
<div><h3>{{ $order->kitchenTicket?->ticket_number ?? 'KOT pending' }}</h3><span class="muted">{{ $order->order_number }}</span></div>
<span class="status-badge {{ $order->status }}">{{ ucfirst($order->status) }}</span>
</div>
<div class="kot-meta">
<strong>{{ $destination }}</strong>
<span>{{ ucfirst(str_replace('_',' ',$order->order_type)) }}</span>
<span>Waiting {{ $order->created_at?->diffForHumans(null, true) ?? '—' }}</span>
@if($order->kitchenTicket?->started_at)<span>Started {{ $order->kitchenTicket->started_at->format('H:i') }}</span>@endif
@if($order->kitchenTicket?->ready_at)<span>Ready {{ $order->kitchenTicket->ready_at->format('H:i') }}</span>@endif
</div>
<div class="kot-items">
@foreach($order->items as $item)
<div class="kot-item"><strong>{{ $item->quantity }}× {{ $item->item_name }}</strong>@if($item->note)<span class="kot-note">Note: {{ $item->note }}</span>@endif</div>
@endforeach
</div>

@php($allTransitions = ['accepted'=>['preparing','cancelled'],'preparing'=>['ready','cancelled'],'ready'=>['served'],'served'=>[],'cancelled'=>[]])
@php($statusOptions = $allTransitions[$order->status] ?? [])
@if($kitchenOnly ?? false)
@php($statusOptions = array_values(array_intersect($statusOptions,['preparing','ready'])))
@endif
@if($statusOptions !== [])
<form class="kot-actions" method="post" action="{{ route('admin.restaurant.orders.status',$order) }}">
@csrf
<label>Next status<select name="status">@foreach($statusOptions as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></label>
<button>Update KOT</button>
</form>
@endif
</div>
@endforeach
</div>
@endif
</section>

@unless($kitchenOnly ?? false)
<section class="panel">
<div class="toolbar"><div><h2 style="margin:0">Recent completed orders</h2><span class="muted">Last 50 served or cancelled orders.</span></div></div>
@if($historyOrders->isEmpty())
<div class="empty-state">No completed restaurant orders yet.</div>
@else
<table class="restaurant-history">
<thead><tr><th>Order / KOT</th><th>Destination & items</th><th>Status</th><th>Total</th><th>Settlement</th></tr></thead>
<tbody>
@foreach($historyOrders as $order)
@php($roomNumbers = $order->folio?->stay?->rooms?->whereNull('released_at')->pluck('room.number')->filter()->implode(', '))
@php($destination = match($order->order_type) {
'dine_in' => $order->restaurantTable ? $order->restaurantTable->name : 'Table unavailable',
'room_service' => $roomNumbers ? 'Room '.$roomNumbers : 'Folio #'.($order->folio_id ?? '—'),
'takeaway' => trim((string)$order->guest_name) !== '' ? 'Takeaway · '.$order->guest_name : 'Takeaway counter',
default => ucfirst(str_replace('_',' ',$order->order_type)),
})
<tr>
<td><strong>{{ $order->order_number }}</strong><br><span class="muted">{{ $order->kitchenTicket?->ticket_number ?? '—' }}</span></td>
<td><strong>{{ $destination }}</strong><br>@foreach($order->items as $item){{ $item->quantity }}× {{ $item->item_name }}@if($item->note) <span class="muted">({{ $item->note }})</span>@endif@if(!$loop->last)<br>@endif @endforeach</td>
<td><span class="status-badge {{ $order->status }}">{{ ucfirst($order->status) }}</span><br><span class="muted">{{ str_replace('_',' ',$order->payment_status) }}</span></td>
<td>₹{{ number_format((float)$order->total,2) }}</td>
<td>
@if($order->order_type === 'room_service' && $order->status === 'served')
<span class="status-badge good">Charged to folio</span>
@elseif($order->order_type !== 'room_service' && $order->payment_status !== 'paid' && $order->status === 'served')
@php($succeededPayments=(float)$order->payments->where('status','succeeded')->sum('amount'))
@php($succeededRefunds=(float)$order->payments->flatMap->refunds->where('status','succeeded')->sum('amount'))
@php($outstanding=max(0,round((float)$order->total-$succeededPayments+$succeededRefunds,2)))
@if($outstanding>0)
<form class="actions" data-restaurant-payment method="post" action="{{ route('admin.payments.store') }}">
@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="target_type" value="restaurant_order">
<input type="hidden" name="target_id" value="{{ $order->id }}">
<input type="hidden" name="amount" value="{{ number_format($outstanding,2,'.','') }}">
<span>Due ₹{{ number_format($outstanding,2) }}</span>
<select name="method"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option></select>
<input name="external_reference" maxlength="190" placeholder="Transaction reference" aria-label="Payment transaction reference">
<button>Pay balance</button>
</form>
@else
<span class="status-badge good">Settled</span>
@endif
@elseif($order->payment_status === 'paid')
<span class="status-badge good">Paid</span>
@else
<span class="muted">No settlement action</span>
@endif
</td>
</tr>
@endforeach
</tbody>
</table>
@endif
</section>
@endunless

<script>
(() => {
  document.querySelectorAll('[data-restaurant-payment]').forEach((paymentForm) => {
    const method = paymentForm.querySelector('select[name="method"]');
    const reference = paymentForm.querySelector('input[name="external_reference"]');
    if (!method || !reference) return;

    const syncReference = () => {
      const needsReference = method.value !== 'cash';
      reference.required = needsReference;
      reference.hidden = !needsReference;
      if (!needsReference) reference.value = '';
    };

    method.addEventListener('change', syncReference);
    syncReference();
  });

  const form = document.getElementById('restaurant-order-form');
  if (!form) return;

  const container = document.getElementById('order-items');
  const template = document.getElementById('order-item-template');
  const add = document.getElementById('add-order-item');
  const type = document.getElementById('restaurant-order-type');
  const search = document.getElementById('menu-search');
  const estimate = document.getElementById('order-estimate');
  const submit = document.getElementById('create-kot-button');
  let index = container.querySelectorAll('[data-item-row]').length;

  const refreshContext = () => {
    const selected = type.value;
    document.querySelectorAll('[data-order-context]').forEach((section) => {
      const active = section.dataset.orderContext === selected;
      section.hidden = !active;
      section.querySelectorAll('[data-context-control]').forEach((control) => {
        control.disabled = !active;
        control.required = active && control.hasAttribute('data-context-required');
      });
    });
  };

  const refreshEstimate = () => {
    let subtotal = 0;
    container.querySelectorAll('[data-item-row]').forEach((row) => {
      const select = row.querySelector('[data-menu-select]');
      const quantity = row.querySelector('[data-quantity]');
      const price = Number(select?.selectedOptions?.[0]?.dataset?.price || 0);
      subtotal += price * Math.max(0, Number(quantity?.value || 0));
    });
    estimate.textContent = 'Estimated subtotal ₹' + subtotal.toFixed(2) + ' before tax';
  };

  const refreshRemoveButtons = () => {
    const rows = container.querySelectorAll('[data-item-row]');
    rows.forEach((row) => {
      const button = row.querySelector('[data-remove-item]');
      if (button) button.disabled = rows.length === 1;
    });
  };

  const applyMenuSearch = () => {
    const query = (search.value || '').trim().toLowerCase();
    container.querySelectorAll('[data-menu-select]').forEach((select) => {
      select.querySelectorAll('option[data-search]').forEach((option) => {
        const selected = option.selected;
        option.hidden = query !== '' && !option.dataset.search.includes(query) && !selected;
      });
    });
  };

  add.addEventListener('click', () => {
    if (container.querySelectorAll('[data-item-row]').length >= 30) return;
    const fragment = template.content.cloneNode(true);
    fragment.querySelectorAll('[data-field]').forEach((field) => {
      field.name = 'items[' + index + '][' + field.dataset.field + ']';
    });
    container.appendChild(fragment);
    index += 1;
    refreshRemoveButtons();
    applyMenuSearch();
    refreshEstimate();
  });

  container.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-item]');
    if (!button || button.disabled) return;
    button.closest('[data-item-row]')?.remove();
    refreshRemoveButtons();
    refreshEstimate();
  });

  container.addEventListener('change', refreshEstimate);
  container.addEventListener('input', refreshEstimate);
  type.addEventListener('change', refreshContext);
  search.addEventListener('input', applyMenuSearch);

  form.addEventListener('submit', () => {
    submit.disabled = true;
    submit.textContent = 'Creating KOT…';
  });

  refreshContext();
  refreshRemoveButtons();
  refreshEstimate();
})();
</script>
@endsection
