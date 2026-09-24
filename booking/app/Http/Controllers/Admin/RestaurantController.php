<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
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
            'guest_phone' => ['nullable', 'string', 'max:30'],
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
                'items' => $data['items'],
            ]);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['restaurant' => $exception->getMessage()]);
        }

        return back()->with('status', 'Restaurant order created and KOT generated.');
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
