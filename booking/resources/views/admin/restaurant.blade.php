@extends('admin.layout')
@section('title','Restaurant')
@section('content')
<h1>Restaurant & KOT</h1>

<section class="panel">
<h2>New order</h2>
<form class="grid" method="post" action="{{ route('admin.restaurant.orders.store') }}">@csrf
<label>Order type<select name="order_type"><option value="dine_in">Dine-in</option><option value="room_service">Room service</option><option value="takeaway">Takeaway</option></select></label>
<label>Table<select name="restaurant_table_id"><option value="">—</option>@foreach($tables as $table)<option value="{{ $table->id }}" @disabled($table->status!=='available')>{{ $table->name }} · {{ $table->status }}</option>@endforeach</select></label>
<label>Guest folio<select name="folio_id"><option value="">—</option>@foreach($folios as $folio)<option value="{{ $folio->id }}">Folio #{{ $folio->id }} / Reservation #{{ $folio->reservation_id }}</option>@endforeach</select></label>
<label>Guest name<input name="guest_name"></label>
<label>Phone<input name="guest_phone"></label>

<div class="full">
<strong>Items</strong>
<div id="order-items">
<div class="item-row" data-item-row>
<label>Menu item<select name="items[0][menu_item_id]" required><option value="">Choose item</option>@foreach($menuItems as $item)<option value="{{ $item->id }}">{{ $item->name }} · ₹{{ number_format((float)$item->price,2) }}</option>@endforeach</select></label>
<label>Qty<input type="number" min="1" max="50" name="items[0][quantity]" value="1" required></label>
<label>Note<input name="items[0][note]"></label>
<button type="button" data-remove-item disabled>Remove</button>
</div>
</div>
<button type="button" id="add-order-item">Add another item</button>
</div>
<div><button>Create order & KOT</button></div>
</form>

<template id="order-item-template">
<div class="item-row" data-item-row>
<label>Menu item<select data-field="menu_item_id" required><option value="">Choose item</option>@foreach($menuItems as $item)<option value="{{ $item->id }}">{{ $item->name }} · ₹{{ number_format((float)$item->price,2) }}</option>@endforeach</select></label>
<label>Qty<input type="number" min="1" max="50" value="1" data-field="quantity" required></label>
<label>Note<input data-field="note"></label>
<button type="button" data-remove-item>Remove</button>
</div>
</template>
</section>

<section class="panel">
<h2>Restaurant tables</h2>
<div class="cards">@foreach($tables as $table)<div class="card"><strong>{{ $table->name }}</strong>{{ $table->status }}@if($table->capacity)<br><span class="muted">Capacity {{ $table->capacity }}</span>@endif</div>@endforeach</div>
</section>

<section class="panel">
<h2>Orders</h2>
<table><thead><tr><th>Order</th><th>Items</th><th>Type</th><th>KOT</th><th>Status</th><th>Total</th><th>Action</th></tr></thead><tbody>
@foreach($orders as $order)
<tr>
<td>{{ $order->order_number }}</td>
<td>@foreach($order->items as $item){{ $item->quantity }}× {{ $item->item_name }}@if(!$loop->last)<br>@endif @endforeach</td>
<td>{{ str_replace('_',' ',$order->order_type) }}</td>
<td>{{ $order->kitchenTicket?->ticket_number }}</td>
<td>{{ $order->status }} / {{ $order->payment_status }}</td>
<td>₹{{ number_format((float)$order->total,2) }}</td>
<td>
@if(!in_array($order->status,['served','cancelled'],true))
<form class="actions" method="post" action="{{ route('admin.restaurant.orders.status',$order) }}">@csrf<select name="status">@foreach(['preparing','ready','served','cancelled'] as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select><button>Update</button></form>
@endif
@if($order->order_type !== 'room_service' && $order->payment_status !== 'paid' && $order->status!=='cancelled')
<form class="actions" method="post" action="{{ route('admin.payments.store') }}">@csrf<input type="hidden" name="target_type" value="restaurant_order"><input type="hidden" name="target_id" value="{{ $order->id }}"><input type="hidden" name="amount" value="{{ $order->total }}"><select name="method"><option>cash</option><option>upi</option><option>card</option></select><button>Pay</button></form>
@endif
</td>
</tr>
@endforeach
</tbody></table>
</section>

<script>
(() => {
  const container = document.getElementById('order-items');
  const template = document.getElementById('order-item-template');
  const add = document.getElementById('add-order-item');
  let index = 1;

  add?.addEventListener('click', () => {
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector('[data-item-row]');
    row.querySelectorAll('[data-field]').forEach((field) => {
      field.name = `items[${index}][${field.dataset.field}]`;
    });
    container.appendChild(fragment);
    index += 1;
  });

  container?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-item]');
    if (button && !button.disabled) button.closest('[data-item-row]')?.remove();
  });
})();
</script>
@endsection
