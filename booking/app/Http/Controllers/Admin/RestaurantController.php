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
    public function index(): View
    {
        return view('admin.restaurant', [
            'menuItems' => RestaurantMenuItem::query()->where('is_active', true)->orderBy('name')->get(),
            'tables' => RestaurantTable::query()->where('is_active', true)->orderBy('code')->get(),
            'folios' => Folio::query()->where('status', 'open')->orderByDesc('id')->get(),
            'orders' => RestaurantOrder::query()
                ->with(['items', 'kitchenTicket'])
                ->orderByDesc('id')
                ->limit(75)
                ->get(),
        ]);
    }

    public function store(Request $request, RestaurantService $service): RedirectResponse
    {
        $data = $request->validate([
            'order_type' => ['required', 'in:dine_in,room_service,takeaway'],
            'folio_id' => ['nullable', 'integer', 'exists:folios,id'],
            'restaurant_table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'guest_name' => ['nullable', 'string', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
            'menu_item_id' => ['required', 'integer', 'exists:restaurant_menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createOrder([
                'order_type' => $data['order_type'],
                'folio_id' => $data['folio_id'] ?? null,
                'restaurant_table_id' => $data['restaurant_table_id'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'items' => [[
                    'menu_item_id' => $data['menu_item_id'],
                    'quantity' => $data['quantity'],
                    'note' => $data['note'] ?? null,
                ]],
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['restaurant' => $exception->getMessage()]);
        }

        return back()->with('status', 'Restaurant order created and KOT generated.');
    }

    public function status(Request $request, RestaurantOrder $order, RestaurantService $service): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:preparing,ready,served,cancelled'],
        ]);

        try {
            $service->changeStatus($order, $data['status']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['restaurant' => $exception->getMessage()]);
        }

        return back()->with('status', 'Restaurant order updated.');
    }
}
