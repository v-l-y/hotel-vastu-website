<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\Invoice;
use App\Models\KitchenTicket;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
use App\Models\Stay;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $admin = $request->attributes->get('admin_user');
        $role = (string) ($admin?->role ?? '');

        $cards = match ($role) {
            'front_desk' => [
                ['label' => 'Confirmed arrivals', 'value' => Reservation::query()->where('status', 'confirmed')->count()],
                ['label' => 'In-house stays', 'value' => Stay::query()->where('status', 'checked_in')->count()],
                ['label' => 'Open folios', 'value' => Folio::query()->where('status', 'open')->count()],
            ],
            'restaurant' => [
                ['label' => 'Active restaurant orders', 'value' => RestaurantOrder::query()
                    ->whereIn('status', ['accepted', 'preparing', 'ready'])
                    ->count()],
                ['label' => 'Unsettled restaurant bills', 'value' => RestaurantOrder::query()
                    ->whereIn('order_type', ['dine_in', 'takeaway'])
                    ->where('status', 'served')
                    ->whereIn('payment_status', ['unpaid', 'partially_paid'])
                    ->count()],
            ],
            'kitchen' => [
                ['label' => 'KOT queue', 'value' => KitchenTicket::query()
                    ->whereIn('status', ['pending', 'preparing', 'ready'])
                    ->count()],
            ],
            'accounts' => [
                ['label' => 'Successful payments', 'value' => Payment::query()->where('status', 'succeeded')->count()],
                ['label' => 'Pending refunds', 'value' => Refund::query()->whereIn('status', ['pending', 'pending_manual'])->count()],
                ['label' => 'Invoices issued', 'value' => Invoice::query()->count()],
            ],
            default => [
                ['label' => 'Confirmed arrivals', 'value' => Reservation::query()->where('status', 'confirmed')->count()],
                ['label' => 'In-house stays', 'value' => Stay::query()->where('status', 'checked_in')->count()],
                ['label' => 'Open folios', 'value' => Folio::query()->where('status', 'open')->count()],
                ['label' => 'Active restaurant orders', 'value' => RestaurantOrder::query()
                    ->whereIn('status', ['accepted', 'preparing', 'ready'])
                    ->count()],
            ],
        };

        return view('admin.dashboard', [
            'admin' => $admin,
            'cards' => $cards,
        ]);
    }
}
