<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\TaxRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RestaurantService
{
    public function __construct(private FolioService $folios)
    {
    }

    public function createOrder(array $data): RestaurantOrder
    {
        return DB::transaction(function () use ($data) {
            $type = $data['order_type'];
            if (! in_array($type, ['dine_in', 'room_service', 'takeaway'], true)) {
                throw new RuntimeException('Unsupported restaurant order type.');
            }

            $folio = null;
            $table = null;

            if ($type === 'room_service') {
                $folio = Folio::query()->whereKey($data['folio_id'] ?? 0)->where('status', 'open')->first();
                if ($folio === null) {
                    throw new RuntimeException('Room service requires an open guest folio.');
                }
            }

            if ($type === 'dine_in') {
                $table = RestaurantTable::query()
                    ->whereKey($data['restaurant_table_id'] ?? 0)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($table === null) {
                    throw new RuntimeException('Dine-in orders require an active restaurant table.');
                }

                if ($table->status !== 'available') {
                    throw new RuntimeException('The selected restaurant table is already occupied.');
                }
            }

            $requestedItems = array_values(array_filter(
                $data['items'] ?? [],
                fn ($item) => ! empty($item['menu_item_id'])
            ));

            if ($requestedItems === []) {
                throw new RuntimeException('At least one restaurant item is required.');
            }

            $lines = [];
            $subtotal = 0.0;

            foreach ($requestedItems as $requested) {
                $quantity = max(1, (int) ($requested['quantity'] ?? 1));
                $item = RestaurantMenuItem::query()
                    ->whereKey((int) $requested['menu_item_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lineTotal = round((float) $item->price * $quantity, 2);
                $subtotal = round($subtotal + $lineTotal, 2);

                $lines[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                    'note' => $requested['note'] ?? null,
                ];
            }

            $taxRate = (float) TaxRule::query()
                ->effectiveOn(today()->toDateString())
                ->whereIn('applies_to', ['restaurant', 'all'])
                ->sum('rate_percent');
            $tax = round($subtotal * ($taxRate / 100), 2);

            $order = RestaurantOrder::query()->create([
                'order_number' => 'RO-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'order_type' => $type,
                'folio_id' => $folio?->id,
                'restaurant_table_id' => $table?->id,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'status' => 'accepted',
                'payment_status' => $type === 'room_service' ? 'room_charge_pending' : 'unpaid',
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => round($subtotal + $tax, 2),
            ]);

            if ($table !== null) {
                $table->update(['status' => 'occupied']);
            }

            $ticket = KitchenTicket::query()->create([
                'ticket_number' => 'KOT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'restaurant_order_id' => $order->id,
                'status' => 'pending',
            ]);

            foreach ($lines as $line) {
                $orderItem = RestaurantOrderItem::query()->create([
                    'restaurant_order_id' => $order->id,
                    'restaurant_menu_item_id' => $line['item']->id,
                    'item_name' => $line['item']->name,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['item']->price,
                    'line_total' => $line['line_total'],
                    'note' => $line['note'],
                ]);

                KitchenTicketItem::query()->create([
                    'kitchen_ticket_id' => $ticket->id,
                    'restaurant_order_item_id' => $orderItem->id,
                ]);
            }

            return $order->load(['items', 'kitchenTicket.items']);
        }, 3);
    }

    public function changeStatus(RestaurantOrder $order, string $nextStatus): RestaurantOrder
    {
        return DB::transaction(function () use ($order, $nextStatus) {
            $order = RestaurantOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $transitions = [
                'accepted' => ['preparing', 'cancelled'],
                'preparing' => ['ready', 'cancelled'],
                'ready' => ['served'],
                'served' => [],
                'cancelled' => [],
            ];

            if (! in_array($nextStatus, $transitions[$order->status] ?? [], true)) {
                throw new RuntimeException('Invalid restaurant order status transition.');
            }

            $ticket = KitchenTicket::query()
                ->where('restaurant_order_id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($nextStatus === 'preparing') {
                $ticket->update(['status' => 'preparing', 'started_at' => now()]);
            } elseif ($nextStatus === 'ready') {
                $ticket->update(['status' => 'ready', 'ready_at' => now()]);
            } elseif ($nextStatus === 'served') {
                $ticket->update(['status' => 'served', 'served_at' => now()]);
            } elseif ($nextStatus === 'cancelled') {
                $ticket->update(['status' => 'cancelled']);
            }

            $order->update(['status' => $nextStatus]);

            if ($nextStatus === 'served' && $order->order_type === 'room_service') {
                $folio = Folio::query()->whereKey($order->folio_id)->lockForUpdate()->firstOrFail();
                $this->folios->addCharge($folio, [
                    'category' => 'restaurant',
                    'description' => 'Room service '.$order->order_number,
                    'quantity' => 1,
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'amount' => $order->total,
                    'source_key' => 'restaurant-order:'.$order->id,
                ]);
                $order->update(['payment_status' => 'charged_to_room']);
            }

            if (
                $order->order_type === 'dine_in'
                && $order->restaurant_table_id !== null
                && in_array($nextStatus, ['served', 'cancelled'], true)
            ) {
                RestaurantTable::query()
                    ->whereKey($order->restaurant_table_id)
                    ->lockForUpdate()
                    ->update(['status' => 'available']);
            }

            return $order->fresh(['items', 'kitchenTicket']);
        }, 3);
    }
}
