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
use Illuminate\Database\QueryException;
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
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? Str::uuid()));
        if (! Str::isUuid($idempotencyKey)) {
            throw new RuntimeException('Restaurant order idempotency key is invalid.');
        }

        $data['idempotency_key'] = $idempotencyKey;

        try {
            return DB::transaction(function () use ($data, $idempotencyKey) {
                $existing = RestaurantOrder::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $this->assertSameIdempotentRequest($existing, $data);

                    return $existing->load(['items', 'kitchenTicket.items']);
                }

                $type = $data['order_type'];
                if (! in_array($type, ['dine_in', 'room_service', 'takeaway'], true)) {
                    throw new RuntimeException('Unsupported restaurant order type.');
                }

                $folio = null;
                $table = null;

                if ($type === 'room_service') {
                    $folio = Folio::query()
                        ->whereKey($data['folio_id'] ?? 0)
                        ->where('status', 'open')
                        ->lockForUpdate()
                        ->first();
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

                $requestedItems = $this->requestedItems($data);
                if ($requestedItems === []) {
                    throw new RuntimeException('At least one restaurant item is required.');
                }

                $lines = [];
                $subtotal = 0.0;

                foreach ($requestedItems as $requested) {
                    $item = RestaurantMenuItem::query()
                        ->whereKey($requested['menu_item_id'])
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if ($item === null) {
                        throw new RuntimeException('A selected restaurant menu item is no longer available.');
                    }

                    $lineTotal = round((float) $item->price * $requested['quantity'], 2);
                    $subtotal = round($subtotal + $lineTotal, 2);

                    $lines[] = [
                        'item' => $item,
                        'quantity' => $requested['quantity'],
                        'line_total' => $lineTotal,
                        'note' => $requested['note'] !== '' ? $requested['note'] : null,
                    ];
                }

                $taxRate = (float) TaxRule::query()
                    ->effectiveOn(today()->toDateString())
                    ->whereIn('applies_to', ['restaurant', 'all'])
                    ->sum('rate_percent');
                $tax = round($subtotal * ($taxRate / 100), 2);

                $directBill = $type !== 'room_service';
                $guestName = $directBill ? trim((string) ($data['guest_name'] ?? '')) : '';
                $guestPhone = $directBill ? trim((string) ($data['guest_phone'] ?? '')) : '';
                $guestEmail = $directBill ? trim((string) ($data['guest_email'] ?? '')) : '';
                $guestGstin = $directBill ? strtoupper(trim((string) ($data['guest_gstin'] ?? ''))) : '';
                $guestBillingAddress = $directBill ? trim((string) ($data['guest_billing_address'] ?? '')) : '';
                $guestBillingState = $directBill ? trim((string) ($data['guest_billing_state'] ?? '')) : '';
                $guestBillingStateCode = $directBill ? trim((string) ($data['guest_billing_state_code'] ?? '')) : '';

                $order = RestaurantOrder::query()->create([
                    'order_number' => 'RO-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                    'idempotency_key' => $idempotencyKey,
                    'order_type' => $type,
                    'folio_id' => $folio?->id,
                    'restaurant_table_id' => $table?->id,
                    'guest_name' => $guestName !== '' ? $guestName : null,
                    'guest_phone' => $guestPhone !== '' ? $guestPhone : null,
                    'guest_email' => $guestEmail !== '' ? $guestEmail : null,
                    'guest_gstin' => $guestGstin !== '' ? $guestGstin : null,
                    'guest_billing_address' => $guestBillingAddress !== '' ? $guestBillingAddress : null,
                    'guest_billing_state' => $guestBillingState !== '' ? $guestBillingState : null,
                    'guest_billing_state_code' => $guestBillingStateCode !== '' ? $guestBillingStateCode : null,
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
        } catch (QueryException $exception) {
            $existing = RestaurantOrder::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            $this->assertSameIdempotentRequest($existing, $data);

            return $existing->load(['items', 'kitchenTicket.items']);
        }
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
                && (
                    $nextStatus === 'cancelled'
                    || ($nextStatus === 'served' && $order->payment_status === 'paid')
                )
            ) {
                RestaurantTable::query()
                    ->whereKey($order->restaurant_table_id)
                    ->lockForUpdate()
                    ->update(['status' => 'available']);
            }

            return $order->fresh(['items', 'kitchenTicket']);
        }, 3);
    }

    private function requestedItems(array $data): array
    {
        return array_values(array_map(
            static fn (array $item): array => [
                'menu_item_id' => (int) $item['menu_item_id'],
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'note' => trim((string) ($item['note'] ?? '')),
            ],
            array_filter(
                $data['items'] ?? [],
                static fn ($item): bool => is_array($item) && ! empty($item['menu_item_id'])
            )
        ));
    }

    private function assertSameIdempotentRequest(RestaurantOrder $order, array $data): void
    {
        $expected = [
            'order_type' => (string) ($data['order_type'] ?? ''),
            'folio_id' => (int) ($data['folio_id'] ?? 0),
            'restaurant_table_id' => (int) ($data['restaurant_table_id'] ?? 0),
            'guest_name' => trim((string) ($data['guest_name'] ?? '')),
            'guest_phone' => trim((string) ($data['guest_phone'] ?? '')),
            'guest_email' => trim((string) ($data['guest_email'] ?? '')),
            'guest_gstin' => strtoupper(trim((string) ($data['guest_gstin'] ?? ''))),
            'guest_billing_address' => trim((string) ($data['guest_billing_address'] ?? '')),
            'guest_billing_state' => trim((string) ($data['guest_billing_state'] ?? '')),
            'guest_billing_state_code' => trim((string) ($data['guest_billing_state_code'] ?? '')),
            'items' => $this->requestedItems($data),
        ];

        $actual = [
            'order_type' => $order->order_type,
            'folio_id' => (int) ($order->folio_id ?? 0),
            'restaurant_table_id' => (int) ($order->restaurant_table_id ?? 0),
            'guest_name' => trim((string) ($order->guest_name ?? '')),
            'guest_phone' => trim((string) ($order->guest_phone ?? '')),
            'guest_email' => trim((string) ($order->guest_email ?? '')),
            'guest_gstin' => strtoupper(trim((string) ($order->guest_gstin ?? ''))),
            'guest_billing_address' => trim((string) ($order->guest_billing_address ?? '')),
            'guest_billing_state' => trim((string) ($order->guest_billing_state ?? '')),
            'guest_billing_state_code' => trim((string) ($order->guest_billing_state_code ?? '')),
            'items' => $order->items()
                ->orderBy('id')
                ->get()
                ->map(static fn (RestaurantOrderItem $item): array => [
                    'menu_item_id' => (int) $item->restaurant_menu_item_id,
                    'quantity' => (int) $item->quantity,
                    'note' => trim((string) ($item->note ?? '')),
                ])
                ->all(),
        ];

        if ($expected !== $actual) {
            throw new RuntimeException('Restaurant order idempotency key was already used for a different request.');
        }
    }
}
