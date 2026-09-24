<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $from = CarbonImmutable::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = CarbonImmutable::parse($request->query('to', now()->toDateString()))->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return view('admin.reports', [
            'from' => $from,
            'to' => $to,
            'paymentsTotal' => (float) Payment::query()
                ->where('status', 'succeeded')
                ->whereBetween('paid_at', [$from, $to])
                ->sum('amount'),
            'bookingsCreated' => Reservation::query()
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'cancelledBookings' => Reservation::query()
                ->where('status', 'cancelled')
                ->whereBetween('updated_at', [$from, $to])
                ->count(),
            'restaurantSales' => (float) RestaurantOrder::query()
                ->where('status', 'served')
                ->whereBetween('updated_at', [$from, $to])
                ->sum('total'),
        ]);
    }
}
