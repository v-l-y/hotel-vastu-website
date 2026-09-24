<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\ReservationNightRate;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\Room;
use App\Models\RoomBlock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $nightQuery = ReservationNightRate::query()
            ->join('reservations', 'reservations.id', '=', 'reservation_night_rates.reservation_id')
            ->whereIn('reservations.status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereBetween('reservation_night_rates.stay_date', [$fromDate, $toDate]);

        $bookedRoomNights = (int) (clone $nightQuery)->sum('reservation_night_rates.quantity');
        $roomRevenue = (float) (clone $nightQuery)->sum('reservation_night_rates.line_total');
        $availableRoomNights = 0;
        for (
            $date = $from->startOfDay();
            $date->lte($to->startOfDay());
            $date = $date->addDay()
        ) {
            $dateString = $date->toDateString();

            $saleableRoomIds = Room::query()
                ->where('status', 'active')
                ->whereDate('created_at', '<=', $dateString)
                ->when(
                    $date->isToday(),
                    fn ($query) => $query->where('housekeeping_status', '!=', 'out_of_order')
                )
                ->pluck('id');

            if ($saleableRoomIds->isEmpty()) {
                continue;
            }

            $blockedRooms = RoomBlock::query()
                ->whereIn('room_id', $saleableRoomIds)
                ->whereDate('starts_on', '<=', $dateString)
                ->whereDate('ends_on', '>', $dateString)
                ->where(function ($query) use ($dateString) {
                    $query
                        ->whereNull('closed_at')
                        ->orWhereDate('closed_at', '>', $dateString);
                })
                ->distinct()
                ->count('room_id');

            $availableRoomNights += max(0, $saleableRoomIds->count() - $blockedRooms);
        }

        $paymentsByMethod = Payment::query()
            ->select('method', DB::raw('SUM(amount) as total'))
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('method')
            ->orderBy('method')
            ->get();

        $topRestaurantItems = RestaurantOrderItem::query()
            ->join('restaurant_orders', 'restaurant_orders.id', '=', 'restaurant_order_items.restaurant_order_id')
            ->join('kitchen_tickets', 'kitchen_tickets.restaurant_order_id', '=', 'restaurant_orders.id')
            ->select(
                'restaurant_order_items.item_name',
                DB::raw('SUM(restaurant_order_items.quantity) as quantity_sold'),
                DB::raw('SUM(restaurant_order_items.line_total) as sales')
            )
            ->where('restaurant_orders.status', 'served')
            ->whereNotNull('kitchen_tickets.served_at')
            ->whereBetween('kitchen_tickets.served_at', [$from, $to])
            ->groupBy('restaurant_order_items.item_name')
            ->orderByDesc('quantity_sold')
            ->limit(10)
            ->get();

        return view('admin.reports', [
            'from' => $from,
            'to' => $to,
            'paymentsTotal' => (float) Payment::query()
                ->where('status', 'succeeded')
                ->whereBetween('paid_at', [$from, $to])
                ->sum('amount'),
            'refundsTotal' => (float) Refund::query()
                ->where('status', 'succeeded')
                ->whereBetween('refunded_at', [$from, $to])
                ->sum('amount'),
            'taxTotal' => (float) Invoice::query()
                ->whereBetween('issued_at', [$from, $to])
                ->sum('tax'),
            'bookingsCreated' => Reservation::query()
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'cancelledBookings' => Reservation::query()
                ->where('status', 'cancelled')
                ->whereBetween('updated_at', [$from, $to])
                ->count(),
            'restaurantSales' => (float) RestaurantOrder::query()
                ->join('kitchen_tickets', 'kitchen_tickets.restaurant_order_id', '=', 'restaurant_orders.id')
                ->where('restaurant_orders.status', 'served')
                ->whereNotNull('kitchen_tickets.served_at')
                ->whereBetween('kitchen_tickets.served_at', [$from, $to])
                ->sum('restaurant_orders.total'),
            'bookedRoomNights' => $bookedRoomNights,
            'occupancyPercent' => $availableRoomNights > 0
                ? round(($bookedRoomNights / $availableRoomNights) * 100, 2)
                : 0,
            'adr' => $bookedRoomNights > 0 ? round($roomRevenue / $bookedRoomNights, 2) : 0,
            'roomRevenue' => $roomRevenue,
            'paymentsByMethod' => $paymentsByMethod,
            'topRestaurantItems' => $topRestaurantItems,
        ]);
    }
}
