<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Services\FrontDeskService;
use App\Services\ReservationLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class FrontDeskController extends Controller
{
    public function index(): View
    {
        return view('admin.front-desk', [
            'reservations' => Reservation::query()
                ->where('status', 'confirmed')
                ->with('rooms')
                ->orderBy('check_in_date')
                ->limit(50)
                ->get(),
            'roomTypes' => RoomType::query()->where('is_active', true)->orderBy('name')->get(),
            'ratePlans' => RatePlan::query()->where('is_active', true)->orderBy('name')->get(),
            'rooms' => Room::query()->where('status', 'active')->with('roomType')->orderBy('number')->get(),
            'stays' => Stay::query()
                ->where('status', 'checked_in')
                ->with(['reservation', 'rooms.room', 'folio'])
                ->orderByDesc('checked_in_at')
                ->get(),
            'folios' => Folio::query()->where('status', 'open')->orderByDesc('id')->get(),
        ]);
    }

    public function modifyReservation(
        Request $request,
        Reservation $reservation,
        ReservationLifecycleService $service
    ): RedirectResponse {
        $data = $request->validate([
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'rate_plan_id' => ['required', 'integer', 'exists:rate_plans,id'],
            'rooms' => ['required', 'integer', 'min:1', 'max:10'],
            'adults' => ['required', 'integer', 'min:1', 'max:30'],
            'children' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        try {
            $service->modifyConfirmed(
                $reservation,
                CarbonImmutable::parse($data['check_in']),
                CarbonImmutable::parse($data['check_out']),
                (int) $data['room_type_id'],
                (int) $data['rate_plan_id'],
                (int) $data['rooms'],
                (int) $data['adults'],
                (int) $data['children']
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['front_desk' => $exception->getMessage()]);
        }

        return back()->with('status', 'Reservation updated and repriced.');
    }

    public function checkIn(Request $request, Reservation $reservation, FrontDeskService $service): RedirectResponse
    {
        $data = $request->validate([
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
        ]);

        try {
            $service->checkIn($reservation, $data['room_ids']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['front_desk' => $exception->getMessage()]);
        }

        return back()->with('status', 'Guest checked in.');
    }

    public function transfer(Request $request, Stay $stay, FrontDeskService $service): RedirectResponse
    {
        $data = $request->validate([
            'from_room_id' => ['required', 'integer', 'exists:rooms,id'],
            'to_room_id' => ['required', 'integer', 'different:from_room_id', 'exists:rooms,id'],
        ]);

        try {
            $service->transferRoom($stay, (int) $data['from_room_id'], (int) $data['to_room_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['front_desk' => $exception->getMessage()]);
        }

        return back()->with('status', 'Room transfer completed.');
    }

    public function extend(Request $request, Stay $stay, FrontDeskService $service): RedirectResponse
    {
        $data = $request->validate([
            'new_checkout' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $service->extendStay($stay, CarbonImmutable::parse($data['new_checkout']));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['front_desk' => $exception->getMessage()]);
        }

        return back()->with('status', 'Stay extended and additional room charges posted.');
    }

    public function housekeeping(Request $request, Room $room, FrontDeskService $service): RedirectResponse
    {
        $data = $request->validate([
            'housekeeping_status' => ['required', 'in:clean,dirty,inspected,out_of_order'],
        ]);

        try {
            $service->updateHousekeeping($room, $data['housekeeping_status']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['front_desk' => $exception->getMessage()]);
        }

        return back()->with('status', 'Housekeeping status updated.');
    }

    public function checkOut(Stay $stay, FrontDeskService $service): RedirectResponse
    {
        try {
            $result = $service->checkOut($stay);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['front_desk' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.invoices.show', $result['invoice'])
            ->with('status', 'Checkout completed.');
    }

    public function cancel(Reservation $reservation, ReservationLifecycleService $service): RedirectResponse
    {
        try {
            $service->cancel($reservation);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['front_desk' => $exception->getMessage()]);
        }

        return back()->with('status', 'Reservation cancelled. Review any collected payment for refund.');
    }

    public function noShow(Reservation $reservation, ReservationLifecycleService $service): RedirectResponse
    {
        try {
            $service->markNoShow($reservation);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['front_desk' => $exception->getMessage()]);
        }

        return back()->with('status', 'Reservation marked no-show.');
    }
}
