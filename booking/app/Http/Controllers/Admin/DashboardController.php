<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
use App\Models\Stay;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.dashboard', [
            'admin' => $request->attributes->get('admin_user'),
            'confirmedReservations' => Reservation::query()->where('status', 'confirmed')->count(),
            'inHouse' => Stay::query()->where('status', 'checked_in')->count(),
            'openFolios' => Folio::query()->where('status', 'open')->count(),
            'activeRestaurantOrders' => RestaurantOrder::query()
                ->whereIn('status', ['accepted', 'preparing', 'ready'])
                ->count(),
        ]);
    }
}
