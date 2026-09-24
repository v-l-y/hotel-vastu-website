<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Services\CustomerMessageService;
use App\Services\InvoiceService;
use App\Services\RestaurantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class RestaurantController extends Controller
{
    public function index(Request $request): View
    {
        $role = (string) ($request->attributes->get('admin_user')?->role ?? '');
        $kitchenOnly = $role === 'kitchen';

        $activeOrders = RestaurantOrder::query()
            ->with([
                'items',
                'kitchenTicket',
                'restaurantTable',
                'folio.stay.rooms.room',
                'payments.refunds',
                'invoice',
            ])
            ->whereIn('status', ['accepted', 'preparing', 'ready'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $historyOrders = $kitchenOnly
            ? collect()
            : RestaurantOrder::query()
                ->with([
                    'items',
                    'kitchenTicket',
                    'restaurantTable',
                    'folio.stay.rooms.room',
                    'payments.refunds',
                'invoice',
                ])
                ->whereIn('status', ['served', 'cancelled'])
                ->orderByDesc('id')
                ->paginate(20, ['*'], 'history_page')
                ->withQueryString();

        return view('admin.restaurant', [
            'kitchenOnly' => $kitchenOnly,
            'menuItems' => $kitchenOnly
                ? collect()
                : RestaurantMenuItem::query()
                    ->with('category')
                    ->where('is_active', true)
                    ->orderBy('restaurant_category_id')
                    ->orderBy('name')
                    ->get(),
            'tables' => $kitchenOnly
                ? collect()
                : RestaurantTable::query()->where('is_active', true)->orderBy('code')->get(),
            'folios' => $kitchenOnly
                ? collect()
                : Folio::query()
                    ->with('stay.rooms.room')
                    ->where('status', 'open')
                    ->orderByDesc('id')
                    ->get(),
            'activeOrders' => $activeOrders,
            'historyOrders' => $historyOrders,
        ]);
    }

    public function store(Request $request, RestaurantService $service): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'order_type' => ['required', 'in:dine_in,room_service,takeaway'],
            'folio_id' => ['nullable', 'integer', 'exists:folios,id'],
            'restaurant_table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'guest_name' => ['nullable', 'string', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() -]{7,30}$/'],
            'guest_email' => ['nullable', 'email:rfc', 'max:190'],
            'guest_gstin' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]$/'],
            'guest_billing_address' => ['nullable', 'string', 'max:500'],
            'guest_billing_state' => ['nullable', 'string', 'max:100'],
            'guest_billing_state_code' => ['nullable', 'digits:2'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.menu_item_id' => ['nullable', 'integer', 'exists:restaurant_menu_items,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createOrder([
                'idempotency_key' => $data['idempotency_key'],
                'order_type' => $data['order_type'],
                'folio_id' => $data['folio_id'] ?? null,
                'restaurant_table_id' => $data['restaurant_table_id'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'guest_email' => $data['guest_email'] ?? null,
                'guest_gstin' => isset($data['guest_gstin']) ? strtoupper(trim($data['guest_gstin'])) : null,
                'guest_billing_address' => $data['guest_billing_address'] ?? null,
                'guest_billing_state' => $data['guest_billing_state'] ?? null,
                'guest_billing_state_code' => $data['guest_billing_state_code'] ?? null,
                'items' => $data['items'],
            ]);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['restaurant' => $exception->getMessage()]);
        }

        return back()->with('status', 'Restaurant order created and KOT generated.');
    }

    public function billingDetails(Request $request, RestaurantOrder $order): RedirectResponse
    {
        if ($order->order_type === 'room_service') {
            return back()->withErrors(['restaurant' => 'Room-service billing details come from the guest folio.']);
        }

        if ($order->invoice()->exists()) {
            return back()->withErrors(['restaurant' => 'Issued invoice identity is immutable. Use a credit note for post-issue accounting adjustments.']);
        }

        $data = $request->validate([
            'guest_name' => ['nullable', 'string', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() -]{7,30}$/'],
            'guest_email' => ['nullable', 'email:rfc', 'max:190'],
            'guest_gstin' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]$/'],
            'guest_billing_address' => ['nullable', 'string', 'max:500'],
            'guest_billing_state' => ['nullable', 'string', 'max:100'],
            'guest_billing_state_code' => ['nullable', 'digits:2'],
        ]);

        $order->update([
            'guest_name' => trim((string) ($data['guest_name'] ?? '')) ?: null,
            'guest_phone' => trim((string) ($data['guest_phone'] ?? '')) ?: null,
            'guest_email' => trim((string) ($data['guest_email'] ?? '')) ?: null,
            'guest_gstin' => strtoupper(trim((string) ($data['guest_gstin'] ?? ''))) ?: null,
            'guest_billing_address' => trim((string) ($data['guest_billing_address'] ?? '')) ?: null,
            'guest_billing_state' => trim((string) ($data['guest_billing_state'] ?? '')) ?: null,
            'guest_billing_state_code' => trim((string) ($data['guest_billing_state_code'] ?? '')) ?: null,
        ]);

        return back()->with('status', 'Restaurant billing details updated.');
    }

    public function issueInvoice(
        RestaurantOrder $order,
        InvoiceService $invoices,
        CustomerMessageService $messages
    ): RedirectResponse {
        try {
            $existing = $order->invoice()->first();
            $invoice = $existing ?? $invoices->createFromRestaurantOrder($order->fresh());

            if ($existing === null) {
                $messages->sendRestaurantInvoice($invoice);
            }
        } catch (RuntimeException $exception) {
            return back()->withErrors(['restaurant' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', $existing === null
                ? 'Restaurant invoice issued and customer delivery processed.'
                : 'Restaurant invoice opened.');
    }

    public function status(Request $request, RestaurantOrder $order, RestaurantService $service): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:preparing,ready,served,cancelled'],
        ]);

        $role = (string) ($request->attributes->get('admin_user')?->role ?? '');
        if ($role === 'kitchen' && ! in_array($data['status'], ['preparing', 'ready'], true)) {
            abort(403);
        }

        try {
            $service->changeStatus($order, $data['status']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['restaurant' => $exception->getMessage()]);
        }

        return back()->with('status', 'Restaurant order updated.');
    }
}
