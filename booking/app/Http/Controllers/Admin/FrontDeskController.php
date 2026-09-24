<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Stay;
use App\Services\FrontDeskService;
use App\Services\ReservationLifecycleService;
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
            'rooms' => Room::query()->where('status', 'active')->with('roomType')->orderBy('number')->get(),
            'stays' => Stay::query()
                ->where('status', 'checked_in')
                ->with(['reservation', 'rooms.room', 'folio'])
                ->orderByDesc('checked_in_at')
                ->get(),
            'folios' => Folio::query()->where('status', 'open')->orderByDesc('id')->get(),
        ]);
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
