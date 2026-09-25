@extends('admin.layout')
@section('title', ($kitchenOnly ?? false) ? 'Kitchen KOT' : 'Restaurant & KOT')

@push('styles')
<style>
.restaurant-heading{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px}
.restaurant-heading h1{margin:0 0 6px}.restaurant-heading p{margin:0}.restaurant-heading-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.order-context[hidden]{display:none}.order-summary{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:10px}
.restaurant-order-modal{width:min(980px,calc(100vw - 28px));max-height:calc(100vh - 28px);padding:0;border:0;border-radius:22px;background:#fff;color:#241f1b;box-shadow:0 28px 80px rgba(28,20,15,.30);overflow:hidden}
.restaurant-order-modal::backdrop{background:rgba(29,22,18,.66);backdrop-filter:blur(3px)}
.order-modal-shell{display:grid;grid-template-rows:auto minmax(0,1fr);max-height:calc(100vh - 28px)}
.order-modal-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding:20px 22px;border-bottom:1px solid #e8e0d9;background:#fcfaf8}
.order-modal-head h2{margin:0 0 5px;font-size:1.45rem}.order-modal-head p{margin:0;max-width:640px}
.order-modal-close{width:42px;height:42px;min-height:42px;padding:0;border-radius:999px;background:#eee8e2;color:#2b211b;font-size:1.35rem;line-height:1;flex:0 0 auto}
.order-modal-body{overflow:auto;padding:20px 22px}
.order-form-stack{display:grid;gap:16px}
.order-section{border:1px solid #e5ddd6;border-radius:16px;padding:16px;background:#fff}
.order-section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:13px}.order-section-head h3{margin:0 0 3px}.order-section-head p{margin:0}
.order-service-grid{display:grid;grid-template-columns:minmax(330px,1.05fr) minmax(280px,.95fr);gap:14px;align-items:start}
.order-service-grid .order-context{min-width:0}
.order-type-field{display:grid;gap:8px}.order-field-label{font-weight:800;color:#342b25}
.order-type-switch{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
.order-type-choice{position:relative;cursor:pointer}.order-type-choice input{position:absolute;opacity:0;pointer-events:none}
.order-type-choice span{display:grid;gap:2px;min-height:66px;padding:12px 13px;border:1px solid #ddd4cc;border-radius:13px;background:#fff;transition:border-color .15s ease,box-shadow .15s ease,background .15s ease}
.order-type-choice strong{font-size:.95rem}.order-type-choice small{color:#766d65;font-size:.78rem;line-height:1.3}
.order-type-choice input:checked + span{border-color:#8e7159;background:#f8f3ee;box-shadow:0 0 0 2px rgba(142,113,89,.12)}
.order-type-choice input:focus-visible + span{outline:2px solid #8e7159;outline-offset:2px}
.order-destination-card{border:1px solid #e5ddd6;border-radius:14px;padding:12px;background:#fcfaf8}
.order-field-help{display:block;margin-top:5px;color:#786f67;font-size:.82rem;font-weight:500}
.order-billing-details{border:1px solid #ebe4de;border-radius:14px;background:#fcfaf8;padding:0 14px}.order-billing-details summary{cursor:pointer;font-weight:800;padding:13px 0}.order-billing-details[open] summary{border-bottom:1px solid #ebe4de;margin-bottom:14px}
.order-billing-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding-bottom:14px}.order-billing-grid .full{grid-column:1/-1}
.menu-toolbar{display:flex;gap:10px;align-items:end;justify-content:space-between;flex-wrap:wrap;margin:0 0 12px}.menu-toolbar label{min-width:min(100%,360px)}
.order-items-list{display:grid;gap:10px}
.order-items-list .item-row{display:grid;grid-template-columns:36px minmax(250px,1.35fr) 78px minmax(170px,1fr) 112px 42px;gap:10px;align-items:end;border:1px solid #e9e1da;border-radius:14px;padding:12px;background:#fcfaf8}
.order-line-number{width:32px;height:32px;border-radius:9px;display:grid;place-items:center;align-self:center;background:#eee8e2;color:#5d5148;font-size:.82rem;font-weight:800}
.order-items-list .item-row label{min-width:0}.order-items-list .item-row select,.order-items-list .item-row input{width:100%;box-sizing:border-box}
.order-line-total{display:grid;gap:3px;align-self:center;text-align:right}.order-line-total span{font-size:.75rem;color:#786f67}.order-line-total strong{font-size:.93rem}
.item-remove{width:42px;height:42px;min-height:42px;padding:0;border-radius:10px;background:#fff2f2;color:#7a1f1f;border:1px solid #efd2d2;font-weight:800}.item-remove:disabled{opacity:.45;cursor:not-allowed}
.order-item-actions{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-top:12px}.order-add-item{background:#f3eee9;color:#2c241f;border:1px solid #ddd3ca}
.order-modal-footer{position:sticky;bottom:0;display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap;padding:16px 22px;border-top:1px solid #e6ded7;background:rgba(255,255,255,.96);backdrop-filter:blur(8px)}
.order-estimate-block{display:grid;gap:3px}.order-estimate-block strong{font-size:1.05rem}.order-footer-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.order-footer-actions .secondary{background:#f0ebe6;color:#2b211b}
.restaurant-tables-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.restaurant-table-card{display:grid;grid-template-columns:auto minmax(0,1fr);gap:12px;align-items:center;border:1px solid #e3dcd5;border-radius:16px;padding:14px;background:#fff;min-height:88px}
.restaurant-table-card.available{background:#f8fcf8;border-color:#c7ddcb}.restaurant-table-card.occupied{background:#fffaf2;border-color:#ead2a8}
.restaurant-table-card .ui-icon-box{width:46px;height:46px;min-width:46px;border-radius:13px}
.restaurant-table-card-main{min-width:0}.restaurant-table-card-head{display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap}
.restaurant-table-name{font-size:1rem;font-weight:800}.restaurant-table-capacity{display:flex;align-items:center;gap:6px;margin-top:6px;color:#6c645d;font-size:.9rem}
.restaurant-table-capacity svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.kot-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.kot-card{border:1px solid #ddd6cf;border-radius:14px;padding:16px;background:#fff}.kot-card-head{display:flex;gap:12px;justify-content:space-between;align-items:flex-start}.kot-card h3{margin:0}.kot-meta{display:flex;gap:8px 14px;flex-wrap:wrap;margin:8px 0 14px;color:#5f5750}.kot-items{display:grid;gap:8px;margin:12px 0}.kot-item{padding:9px 10px;border-radius:9px;background:#f8f5f1}.kot-note{display:block;margin-top:4px;font-weight:700;color:#7a4a08}.kot-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:14px}.status-badge.accepted,.status-badge.preparing{background:#fff2df;color:#7a4a08}.status-badge.ready,.status-badge.served{background:#e8f5ea;color:#245d2d}.status-badge.cancelled{background:#fff0f0;color:#771717}.restaurant-history td{min-width:110px}.restaurant-history td:nth-child(2){min-width:180px}.empty-state{padding:22px;text-align:center;border:1px dashed #cfc5bc;border-radius:12px;color:#6f675f}
@media(max-width:980px){.restaurant-tables-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.order-items-list .item-row{grid-template-columns:36px minmax(0,1fr) 78px 110px 42px}.order-items-list .item-row label:nth-of-type(3){grid-column:2/-1}.order-line-total{grid-column:4}.item-remove{grid-column:5}}
@media(max-width:820px){.order-service-grid{grid-template-columns:1fr}.order-billing-grid{grid-template-columns:1fr}.order-billing-grid .full{grid-column:auto}}
@media(max-width:760px){.kot-grid{grid-template-columns:1fr}.restaurant-heading{align-items:stretch}.restaurant-heading-actions{width:100%}.restaurant-heading-actions .button-link{flex:1}.menu-toolbar label{min-width:100%}.restaurant-order-modal{width:calc(100vw - 16px);max-height:calc(100vh - 16px);border-radius:16px}.order-modal-shell{max-height:calc(100vh - 16px)}.order-modal-head,.order-modal-body,.order-modal-footer{padding-left:16px;padding-right:16px}.order-modal-footer{align-items:stretch}.order-estimate-block{width:100%}.order-footer-actions{width:100%}.order-footer-actions>*{flex:1}.order-type-switch{grid-template-columns:1fr}.order-type-choice span{min-height:auto}.order-items-list .item-row{grid-template-columns:32px minmax(0,1fr) 70px}.order-items-list .item-row label:nth-of-type(1){grid-column:2/-1}.order-items-list .item-row label:nth-of-type(2){grid-column:2}.order-items-list .item-row label:nth-of-type(3){grid-column:2/-1}.order-line-total{grid-column:3;grid-row:2;text-align:right}.item-remove{grid-column:3;grid-row:3;justify-self:end}.restaurant-tables-grid{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="restaurant-heading">
<div>
<h1>{{ ($kitchenOnly ?? false) ? 'Kitchen KOT' : 'Restaurant & KOT' }}</h1>
<p class="muted">{{ ($kitchenOnly ?? false) ? 'Oldest active tickets appear first. Update only the kitchen stages shown on each ticket.' : 'Create restaurant orders, follow KOT progress, settle bills, and keep table status in sync.' }}</p>
</div>
<div class="restaurant-heading-actions">
<?php if (!($kitchenOnly ?? false)): ?>
<button type="button" class="button-link primary" data-open-order-modal>+ New order</button>
<?php endif; ?>
<span class="status-badge {{ $activeOrders->isEmpty() ? 'good' : 'warn' }}">{{ $activeOrders->count() }} active</span>
</div>
</div>

<?php if (!($kitchenOnly ?? false)): ?>
<?php
$selectedType = old('order_type', 'dine_in');
$menuGroups = $menuItems->groupBy(fn ($item) => $item->category?->name ?? 'Menu');
$oldItems = old('items', [['menu_item_id' => '', 'quantity' => 1, 'note' => '']]);
?>
<dialog id="restaurant-order-modal" class="restaurant-order-modal" data-order-modal-auto-open="{{ old('idempotency_key') !== null ? '1' : '0' }}" aria-labelledby="restaurant-order-title">
<div class="order-modal-shell">
<div class="order-modal-head">
<div>
<h2 id="restaurant-order-title">New restaurant order</h2>
<p class="muted">Choose the service type, add items, and create the KOT. Only relevant fields are submitted.</p>
</div>
<button type="button" class="order-modal-close" data-close-order-modal aria-label="Close new order">×</button>
</div>

<form id="restaurant-order-form" method="post" action="{{ route('admin.restaurant.orders.store') }}">
@csrf
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

<div class="order-modal-body">
<div class="order-form-stack">
<section class="order-section">
<div class="order-section-head">
<div><h3>Service details</h3><p class="muted">Start with where this order is going.</p></div>
</div>
<div class="order-service-grid">
<div class="order-type-field">
<span class="order-field-label">Order type</span>
<div class="order-type-switch" role="radiogroup" aria-label="Order type">
<label class="order-type-choice">
<input type="radio" name="order_type" value="dine_in" data-order-type-option <?= $selectedType === 'dine_in' ? 'checked' : '' ?>>
<span><strong>Dine-in</strong><small>Seat the guest at a restaurant table.</small></span>
</label>
<label class="order-type-choice">
<input type="radio" name="order_type" value="room_service" data-order-type-option <?= $selectedType === 'room_service' ? 'checked' : '' ?>>
<span><strong>Room service</strong><small>Charge the served order to an in-house folio.</small></span>
</label>
<label class="order-type-choice">
<input type="radio" name="order_type" value="takeaway" data-order-type-option <?= $selectedType === 'takeaway' ? 'checked' : '' ?>>
<span><strong>Takeaway</strong><small>Counter order with optional customer details.</small></span>
</label>
</div>
<span class="order-field-help">Choose the service first; only matching destination fields stay active.</span>
</div>

<div class="order-context order-destination-card" data-order-context="dine_in">
<label>Restaurant table
<select name="restaurant_table_id" data-context-control data-context-required>
<option value="">Choose available table</option>
<?php foreach ($tables as $table): ?>
<option value="{{ $table->id }}" <?= (string) old('restaurant_table_id') === (string) $table->id ? 'selected' : '' ?> <?= $table->status !== 'available' ? 'disabled' : '' ?>>
{{ $table->name }} · {{ ucfirst($table->status) }}<?php if ($table->capacity): ?> · {{ $table->capacity }} seats<?php endif; ?>
</option>
<?php endforeach; ?>
</select>
<span class="order-field-help">Only currently available tables can be selected.</span>
</label>
</div>

<div class="order-context order-destination-card" data-order-context="room_service">
<label>Guest folio / room
<select name="folio_id" data-context-control data-context-required>
<option value="">Choose in-house guest folio</option>
<?php foreach ($folios as $folio): ?>
<?php
$folioRoomAssignments = $folio->stay?->rooms ?? collect();
$folioRooms = $folioRoomAssignments->whereNull('released_at')->pluck('room.number')->filter()->implode(', ');
if ($folioRooms === '') {
    $folioRooms = $folioRoomAssignments->pluck('room.number')->filter()->implode(', ');
}
?>
<option value="{{ $folio->id }}" <?= (string) old('folio_id') === (string) $folio->id ? 'selected' : '' ?>>
Folio #{{ $folio->id }} · Reservation #{{ $folio->reservation_id }}<?php if ($folioRooms !== ''): ?> · Room {{ $folioRooms }}<?php endif; ?>
</option>
<?php endforeach; ?>
</select>
<span class="order-field-help">Room-service charges are posted to the selected in-house folio after service.</span>
</label>
</div>
</div>
</section>

<div class="order-context" data-order-context="direct">
<details class="order-billing-details">
<summary>Customer & billing details <span class="muted">Optional</span></summary>
<div class="order-billing-grid">
<label>Customer name<input name="guest_name" value="{{ old('guest_name') }}" maxlength="160" data-context-control autocomplete="name" placeholder="Customer name"></label>
<label>Registered mobile<input name="guest_phone" value="{{ old('guest_phone') }}" maxlength="30" data-context-control autocomplete="tel" placeholder="+91 98765 43210"></label>
<label>Email / Gmail<input type="email" name="guest_email" value="{{ old('guest_email') }}" maxlength="190" data-context-control autocomplete="email" placeholder="guest@gmail.com"></label>
<label>Customer GSTIN<input name="guest_gstin" value="{{ old('guest_gstin') }}" maxlength="20" data-context-control autocomplete="off" placeholder="For business GST invoice"></label>
<label class="full">Billing address<textarea name="guest_billing_address" rows="2" maxlength="500" data-context-control placeholder="Optional unless required for GST invoice">{{ old('guest_billing_address') }}</textarea></label>
<label>Billing State<input name="guest_billing_state" value="{{ old('guest_billing_state') }}" maxlength="100" data-context-control placeholder="e.g. Bihar"></label>
<label>State code<input name="guest_billing_state_code" value="{{ old('guest_billing_state_code') }}" maxlength="2" inputmode="numeric" data-context-control placeholder="2 digits"></label>
</div>
</details>
</div>

<section class="order-section">
<div class="order-section-head">
<div><h3>Order items</h3><p class="muted">Kitchen notes are printed on the KOT for the relevant item.</p></div>
<label>Find menu item<input type="search" id="menu-search" placeholder="Search item or category" autocomplete="off"></label>
</div>

<div id="order-items" class="order-items-list">
<?php foreach ($oldItems as $index => $oldItem): ?>
<div class="item-row" data-item-row>
<span class="order-line-number" data-line-number>{{ $index + 1 }}</span>
<label>Menu item
<select name="items[{{ $index }}][menu_item_id]" data-menu-select required>
<option value="">Choose item</option>
<?php foreach ($menuGroups as $categoryName => $items): ?>
<optgroup label="{{ $categoryName }}">
<?php foreach ($items as $item): ?>
<option value="{{ $item->id }}" data-price="{{ number_format((float)$item->price,2,'.','') }}" data-search="{{ strtolower($categoryName.' '.$item->name) }}" <?= (string) ($oldItem['menu_item_id'] ?? '') === (string) $item->id ? 'selected' : '' ?>>{{ $item->name }} · ₹{{ number_format((float)$item->price,2) }}{{ $item->is_vegetarian ? ' · Veg' : '' }}</option>
<?php endforeach; ?>
</optgroup>
<?php endforeach; ?>
</select>
</label>
<label>Qty<input type="number" min="1" max="50" name="items[{{ $index }}][quantity]" value="{{ $oldItem['quantity'] ?? 1 }}" data-quantity required></label>
<label>Kitchen note<input name="items[{{ $index }}][note]" value="{{ $oldItem['note'] ?? '' }}" maxlength="500" placeholder="e.g. no onion"></label>
<div class="order-line-total"><span>Line total</span><strong data-line-total>₹0.00</strong></div>
<button type="button" class="item-remove" data-remove-item aria-label="Remove item" <?= count($oldItems) === 1 ? 'disabled' : '' ?>>×</button>
</div>
<?php endforeach; ?>
</div>

<div class="order-item-actions">
<button type="button" class="order-add-item" id="add-order-item">+ Add another item</button>
<span class="muted">Maximum 30 lines per order.</span>
</div>
</section>
</div>
</div>

<div class="order-modal-footer">
<div class="order-estimate-block">
<span class="muted">Estimated subtotal</span>
<strong id="order-estimate">₹0.00 before tax</strong>
</div>
<div class="order-footer-actions">
<button type="button" class="secondary" data-close-order-modal>Cancel</button>
<button type="submit" id="create-kot-button">Create order & KOT</button>
</div>
</div>
</form>

<template id="order-item-template">
<div class="item-row" data-item-row>
<span class="order-line-number" data-line-number>1</span>
<label>Menu item
<select data-field="menu_item_id" data-menu-select required>
<option value="">Choose item</option>
<?php foreach ($menuGroups as $categoryName => $items): ?>
<optgroup label="{{ $categoryName }}">
<?php foreach ($items as $item): ?>
<option value="{{ $item->id }}" data-price="{{ number_format((float)$item->price,2,'.','') }}" data-search="{{ strtolower($categoryName.' '.$item->name) }}">{{ $item->name }} · ₹{{ number_format((float)$item->price,2) }}{{ $item->is_vegetarian ? ' · Veg' : '' }}</option>
<?php endforeach; ?>
</optgroup>
<?php endforeach; ?>
</select>
</label>
<label>Qty<input type="number" min="1" max="50" value="1" data-field="quantity" data-quantity required></label>
<label>Kitchen note<input data-field="note" maxlength="500" placeholder="e.g. no onion"></label>
<div class="order-line-total"><span>Line total</span><strong data-line-total>₹0.00</strong></div>
<button type="button" class="item-remove" data-remove-item aria-label="Remove item">×</button>
</div>
</template>
</div>
</dialog>

<section class="panel">
<div class="toolbar">
<div class="section-title">
@include('admin.partials.icon',['name'=>'restaurant-table'])
<div><h2 style="margin:0">Restaurant tables</h2><span class="muted">Occupied tables release on cancellation or after a served bill is fully paid.</span></div>
</div>
</div>
<div class="restaurant-tables-grid">
<?php if ($tables->isEmpty()): ?>
<div class="empty-state full">No active restaurant tables configured.</div>
<?php else: ?>
<?php foreach ($tables as $table): ?>
<div class="restaurant-table-card {{ $table->status === 'available' ? 'available' : 'occupied' }}" data-table-card>
@include('admin.partials.icon',['name'=>'restaurant-table'])
<div class="restaurant-table-card-main">
<div class="restaurant-table-card-head">
<span class="restaurant-table-name">{{ $table->name }}</span>
<span class="status-badge {{ $table->status === 'available' ? 'good' : 'warn' }}">{{ ucfirst($table->status) }}</span>
</div>
<?php if ($table->capacity): ?>
<div class="restaurant-table-capacity">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3 20v-1c0-3 2-5 5-5M21 20v-1c0-3-2-5-5-5M9 20h6"/></svg>
<span>Seats {{ $table->capacity }}</span>
</div>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</section>
<?php endif; ?>

<section class="panel">
<div class="toolbar">
<div><h2 style="margin:0">{{ ($kitchenOnly ?? false) ? 'Active Orders / KOT queue' : 'Active orders & KOT' }}</h2><span class="muted">Oldest waiting order is shown first.</span></div>
</div>

<?php if ($activeOrders->isEmpty()): ?>
<div class="empty-state">No active KOTs.</div>
<?php else: ?>
<div class="kot-grid">
<?php foreach ($activeOrders as $order): ?>
<?php
$roomAssignments = $order->folio?->stay?->rooms ?? collect();
$roomNumbers = $roomAssignments->whereNull('released_at')->pluck('room.number')->filter()->implode(', ');
if ($roomNumbers === '') {
    $roomNumbers = $roomAssignments->pluck('room.number')->filter()->implode(', ');
}
$destination = match ($order->order_type) {
    'dine_in' => $order->restaurantTable ? $order->restaurantTable->name : 'Table unavailable',
    'room_service' => $roomNumbers !== '' ? 'Room '.$roomNumbers : 'Folio #'.($order->folio_id ?? '—'),
    'takeaway' => trim((string) $order->guest_name) !== '' ? 'Takeaway · '.$order->guest_name : 'Takeaway counter',
    default => ucfirst(str_replace('_', ' ', $order->order_type)),
};
$allTransitions = [
    'accepted' => ['preparing', 'cancelled'],
    'preparing' => ['ready', 'cancelled'],
    'ready' => ['served'],
    'served' => [],
    'cancelled' => [],
];
$statusOptions = $allTransitions[$order->status] ?? [];
if ($kitchenOnly ?? false) {
    $statusOptions = array_values(array_intersect($statusOptions, ['preparing', 'ready']));
}
?>
<div class="kot-card">
<div class="kot-card-head">
<div><h3>{{ $order->kitchenTicket?->ticket_number ?? 'KOT pending' }}</h3><span class="muted">{{ $order->order_number }}</span></div>
<span class="status-badge {{ $order->status }}">{{ ucfirst($order->status) }}</span>
</div>
<div class="kot-meta">
<strong>{{ $destination }}</strong>
<span>{{ ucfirst(str_replace('_',' ',$order->order_type)) }}</span>
<span>Waiting {{ $order->created_at?->diffForHumans() ?? '—' }}</span>
<?php if ($order->kitchenTicket?->started_at): ?><span>Started {{ $order->kitchenTicket->started_at->format('H:i') }}</span><?php endif; ?>
<?php if ($order->kitchenTicket?->ready_at): ?><span>Ready {{ $order->kitchenTicket->ready_at->format('H:i') }}</span><?php endif; ?>
</div>
<div class="kot-items">
<?php foreach ($order->items as $item): ?>
<div class="kot-item">
<strong>{{ $item->quantity }}× {{ $item->item_name }}</strong>
<?php if ($item->note): ?><span class="kot-note">Note: {{ $item->note }}</span><?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<?php if ($statusOptions !== []): ?>
<form class="kot-actions" method="post" action="{{ route('admin.restaurant.orders.status',$order) }}">
@csrf
<label>Next status
<select name="status">
<?php foreach ($statusOptions as $status): ?>
<option value="{{ $status }}">{{ $status }}</option>
<?php endforeach; ?>
</select>
</label>
<button>Update KOT</button>
</form>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<?php if (!($kitchenOnly ?? false)): ?>
<section class="panel" id="restaurant-history">
<div class="toolbar">
<div class="section-title">
@include('admin.partials.icon',['name'=>'table'])
<div><h2 style="margin:0">Completed order history</h2><span class="muted">Served and cancelled orders, newest first.</span></div>
</div>
<span class="status-badge">{{ $historyOrders->total() }} total</span>
</div>
<?php if ($historyOrders->count() === 0): ?>
<div class="empty-state">No completed restaurant orders yet.</div>
<?php else: ?>
<div class="table-wrap">
<table class="restaurant-history">
<thead><tr><th>Order / KOT</th><th>Destination & items</th><th>Status</th><th>Total</th><th>Settlement</th></tr></thead>
<tbody>
<?php foreach ($historyOrders as $order): ?>
<?php
$roomAssignments = $order->folio?->stay?->rooms ?? collect();
$roomNumbers = $roomAssignments->whereNull('released_at')->pluck('room.number')->filter()->implode(', ');
if ($roomNumbers === '') {
    $roomNumbers = $roomAssignments->pluck('room.number')->filter()->implode(', ');
}
$destination = match ($order->order_type) {
    'dine_in' => $order->restaurantTable ? $order->restaurantTable->name : 'Table unavailable',
    'room_service' => $roomNumbers !== '' ? 'Room '.$roomNumbers : 'Folio #'.($order->folio_id ?? '—'),
    'takeaway' => trim((string) $order->guest_name) !== '' ? 'Takeaway · '.$order->guest_name : 'Takeaway counter',
    default => ucfirst(str_replace('_', ' ', $order->order_type)),
};
$succeededPayments = (float) $order->payments->where('status', 'succeeded')->sum('amount');
$succeededRefunds = (float) $order->payments->flatMap->refunds->where('status', 'succeeded')->sum('amount');
$outstanding = max(0, round((float) $order->total - $succeededPayments + $succeededRefunds, 2));
?>
<tr>
<td><strong>{{ $order->order_number }}</strong><br><span class="muted">{{ $order->kitchenTicket?->ticket_number ?? '—' }}</span></td>
<td>
<strong>{{ $destination }}</strong><br>
<?php foreach ($order->items as $itemIndex => $item): ?>
{{ $item->quantity }}× {{ $item->item_name }}<?php if ($item->note): ?> <span class="muted">({{ $item->note }})</span><?php endif; ?>
<?php if ($itemIndex < $order->items->count() - 1): ?><br><?php endif; ?>
<?php endforeach; ?>
</td>
<td><span class="status-badge {{ $order->status }}">{{ ucfirst($order->status) }}</span><br><span class="muted">{{ str_replace('_',' ',$order->payment_status) }}</span></td>
<td>₹{{ number_format((float)$order->total,2) }}</td>
<td>
<?php if ($order->order_type === 'room_service' && $order->status === 'served'): ?>
<span class="status-badge good">Charged to folio</span>
<?php elseif ($order->order_type !== 'room_service' && $order->payment_status !== 'paid' && $order->status === 'served' && $outstanding > 0): ?>
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
<?php elseif ($order->payment_status === 'paid' || $outstanding <= 0): ?>
<span class="status-badge good">Paid</span>
<?php else: ?>
<span class="muted">No settlement action</span>
<?php endif; ?>

<?php if ($order->order_type !== 'room_service' && $order->status === 'served'): ?>
<div class="actions" style="margin-top:8px">
@if($order->invoice)
<a class="button-link primary" href="{{ route('admin.invoices.show',$order->invoice) }}">Print bill</a>
@elseif($order->payment_status === 'paid' || $outstanding <= 0)
<form method="post" action="{{ route('admin.restaurant.orders.invoice',$order) }}">@csrf<button type="submit">Issue bill</button></form>
@endif
</div>

@if(!$order->invoice)
<details class="action-menu" style="margin-top:8px">
<summary>Billing details</summary>
<form class="grid" method="post" action="{{ route('admin.restaurant.orders.billing',$order) }}">
@csrf
<label>Customer name<input name="guest_name" value="{{ $order->guest_name }}" maxlength="160"></label>
<label>Mobile<input name="guest_phone" value="{{ $order->guest_phone }}" maxlength="30"></label>
<label>Email / Gmail<input type="email" name="guest_email" value="{{ $order->guest_email }}" maxlength="190"></label>
<label>GSTIN<input name="guest_gstin" value="{{ $order->guest_gstin }}" maxlength="20"></label>
<label class="full">Billing address<textarea name="guest_billing_address" rows="2" maxlength="500">{{ $order->guest_billing_address }}</textarea></label>
<label>Billing State<input name="guest_billing_state" value="{{ $order->guest_billing_state }}" maxlength="100"></label>
<label>State code<input name="guest_billing_state_code" value="{{ $order->guest_billing_state_code }}" maxlength="2" inputmode="numeric"></label>
<div class="full"><button type="submit">Save billing details</button></div>
</form>
</details>
@endif
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
@include('admin.partials.pagination',['paginator'=>$historyOrders,'label'=>'Restaurant order history pagination','fragment'=>'restaurant-history'])
<?php endif; ?>
</section>
<?php endif; ?>

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

  const modal = document.getElementById('restaurant-order-modal');
  document.querySelectorAll('[data-open-order-modal]').forEach((button) => {
    button.addEventListener('click', () => modal?.showModal());
  });
  document.querySelectorAll('[data-close-order-modal]').forEach((button) => {
    button.addEventListener('click', () => modal?.close());
  });
  modal?.addEventListener('click', (event) => {
    if (event.target === modal) modal.close();
  });

  const form = document.getElementById('restaurant-order-form');
  if (!form) return;

  const container = document.getElementById('order-items');
  const template = document.getElementById('order-item-template');
  const add = document.getElementById('add-order-item');
  const typeOptions = Array.from(document.querySelectorAll('[data-order-type-option]'));
  const search = document.getElementById('menu-search');
  const estimate = document.getElementById('order-estimate');
  const submit = document.getElementById('create-kot-button');
  let index = container.querySelectorAll('[data-item-row]').length;

  const refreshContext = () => {
    const selected = typeOptions.find((option) => option.checked)?.value || 'dine_in';
    document.querySelectorAll('[data-order-context]').forEach((section) => {
      const active = section.dataset.orderContext === selected || (section.dataset.orderContext === 'direct' && selected !== 'room_service');
      section.hidden = !active;
      section.querySelectorAll('[data-context-control]').forEach((control) => {
        control.disabled = !active;
        control.required = active && control.hasAttribute('data-context-required');
      });
    });
  };

  const refreshEstimate = () => {
    let subtotal = 0;
    container.querySelectorAll('[data-item-row]').forEach((row, rowIndex) => {
      const select = row.querySelector('[data-menu-select]');
      const quantity = row.querySelector('[data-quantity]');
      const price = Number(select?.selectedOptions?.[0]?.dataset?.price || 0);
      const lineTotal = price * Math.max(0, Number(quantity?.value || 0));
      subtotal += lineTotal;

      const number = row.querySelector('[data-line-number]');
      const total = row.querySelector('[data-line-total]');
      if (number) number.textContent = String(rowIndex + 1);
      if (total) total.textContent = '₹' + lineTotal.toFixed(2);
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
        option.hidden = query !== '' && !option.dataset.search.includes(query) && !option.selected;
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
  typeOptions.forEach((option) => option.addEventListener('change', refreshContext));
  search.addEventListener('input', applyMenuSearch);

  form.addEventListener('submit', () => {
    submit.disabled = true;
    submit.textContent = 'Creating KOT…';
  });

  refreshContext();
  refreshRemoveButtons();
  refreshEstimate();

  if (modal?.dataset.orderModalAutoOpen === '1') {
    modal.showModal();
  }
})();
</script>
@endsection
