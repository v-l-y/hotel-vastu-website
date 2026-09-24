<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationFeedback;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\StayRoom;
use App\Services\CustomerMessageService;
use App\Services\FrontDeskService;
use App\Services\ReservationLifecycleService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class FrontDeskController extends Controller
{
    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'overview');
        if (! in_array($tab, ['overview', 'arrivals', 'in-house', 'reservations', 'housekeeping'], true)) {
            $tab = 'overview';
        }

        $search = trim((string) $request->query('q', ''));

        $allReservations = Reservation::query()
            ->where('status', 'confirmed')
            ->with(['rooms.roomType', 'guestLinks.guest'])
            ->orderBy('check_in_date')
            ->limit(100)
            ->get();

        $reservations = $allReservations;

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $reservations = $allReservations->filter(function (Reservation $reservation) use ($needle) {
                $guest = $reservation->guestLinks->first()?->guest;
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $reservation->booking_number,
                    $guest?->first_name,
                    $guest?->last_name,
                    $guest?->phone,
                    $guest?->email,
                ])));

                return str_contains($haystack, $needle);
            })->values();
        }

        $rooms = Room::query()
            ->where('status', 'active')
            ->with('roomType')
            ->orderBy('number')
            ->get();

        $allStays = Stay::query()
            ->where('status', 'checked_in')
            ->with(['reservation.guestLinks.guest', 'rooms.room.roomType', 'folio'])
            ->orderByDesc('checked_in_at')
            ->get();

        $stays = $allStays;

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $stays = $allStays->filter(function (Stay $stay) use ($needle) {
                $guest = $stay->reservation->guestLinks->first()?->guest;
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $stay->reservation->booking_number,
                    $guest?->first_name,
                    $guest?->last_name,
                    $guest?->phone,
                    $guest?->email,
                    $stay->rooms->whereNull('released_at')->pluck('room.number')->join(' '),
                ])));

                return str_contains($haystack, $needle);
            })->values();
        }

        $occupiedRoomIds = StayRoom::query()
            ->whereNull('released_at')
            ->whereHas('stay', fn ($query) => $query->where('status', 'checked_in'))
            ->pluck('room_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $activeBlocks = RoomBlock::query()
            ->where('status', 'active')
            ->get();

        $readyUnoccupiedRooms = $rooms
            ->filter(fn (Room $room) => in_array($room->housekeeping_status, ['clean', 'inspected'], true))
            ->reject(fn (Room $room) => in_array((int) $room->id, $occupiedRoomIds, true))
            ->values();

        $checkInRoomsByReservation = [];
        $checkInWindowOpenByReservation = [];
        $checkInReadyByReservation = [];

        foreach ($allReservations as $reservation) {
            $windowOpen = ! today()->lt($reservation->check_in_date)
                && today()->lt($reservation->check_out_date)
                && $reservation->pricing_status === 'priced';

            $checkInWindowOpenByReservation[$reservation->id] = $windowOpen;

            if (! $windowOpen) {
                $checkInRoomsByReservation[$reservation->id] = collect();
                $checkInReadyByReservation[$reservation->id] = false;
                continue;
            }

            $requiredByType = $reservation->rooms
                ->groupBy('room_type_id')
                ->map(fn ($rows) => (int) $rows->sum('quantity'));

            $eligible = $readyUnoccupiedRooms
                ->filter(fn (Room $room) => $requiredByType->has($room->room_type_id))
                ->reject(fn (Room $room) => $this->roomBlockedForRange(
                    $activeBlocks,
                    $room->id,
                    $reservation->check_in_date,
                    $reservation->check_out_date
                ))
                ->values();

            $checkInRoomsByReservation[$reservation->id] = $eligible;
            $checkInReadyByReservation[$reservation->id] = $requiredByType->every(
                fn (int $required, $roomTypeId) => $eligible
                    ->where('room_type_id', (int) $roomTypeId)
                    ->count() >= $required
            );
        }

        $transferRoomsByAssignment = [];

        foreach ($allStays as $stay) {
            foreach ($stay->rooms->whereNull('released_at') as $assignment) {
                $transferRoomsByAssignment[$assignment->id] = $readyUnoccupiedRooms
                    ->filter(fn (Room $room) => $room->room_type_id === $assignment->room->room_type_id)
                    ->reject(fn (Room $room) => $room->id === $assignment->room_id)
                    ->reject(fn (Room $room) => $this->roomBlockedForRange(
                        $activeBlocks,
                        $room->id,
                        today(),
                        $stay->reservation->check_out_date
                    ))
                    ->values();
            }
        }

        $arrivalCountToday = $allReservations
            ->filter(fn (Reservation $reservation) => $reservation->check_in_date->isToday())
            ->count();

        $departureCountToday = $allStays
            ->filter(fn (Stay $stay) => $stay->reservation->check_out_date->isToday())
            ->count();

        $arrivalsToday = $reservations
            ->filter(fn (Reservation $reservation) => $reservation->check_in_date->isToday())
            ->values();

        return view('admin.front-desk', [
            'tab' => $tab,
            'search' => $search,
            'showNewBooking' => $request->boolean('new'),
            'reservations' => $reservations,
            'arrivalsToday' => $arrivalsToday,
            'arrivalCountToday' => $arrivalCountToday,
            'inHouseCount' => $allStays->count(),
            'departureCountToday' => $departureCountToday,
            'readyRoomCount' => $readyUnoccupiedRooms->count(),
            'occupiedRoomIds' => $occupiedRoomIds,
            'dirtyRoomCount' => $rooms->where('housekeeping_status', 'dirty')->count(),
            'outOfOrderRoomCount' => $rooms->where('housekeeping_status', 'out_of_order')->count(),
            'roomTypes' => RoomType::query()->where('is_active', true)->orderBy('name')->get(),
            'ratePlans' => RatePlan::query()->where('is_active', true)->orderBy('name')->get(),
            'rooms' => $rooms,
            'stays' => $stays,
            'folios' => Folio::query()->where('status', 'open')->orderByDesc('id')->get(),
            'checkInRoomsByReservation' => $checkInRoomsByReservation,
            'checkInWindowOpenByReservation' => $checkInWindowOpenByReservation,
            'checkInReadyByReservation' => $checkInReadyByReservation,
            'transferRoomsByAssignment' => $transferRoomsByAssignment,
            'feedbacks' => ReservationFeedback::query()
                ->whereNotNull('submitted_at')
                ->with('reservation')
                ->latest('submitted_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function createReservation(
        Request $request,
        ReservationService $reservations,
        CustomerMessageService $messages
    ): RedirectResponse {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+() -]{7,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'room_type_id' => [
                'required',
                'integer',
                Rule::exists('room_types', 'id')->where('is_active', true),
            ],
            'rate_plan_id' => [
                'required',
                'integer',
                Rule::exists('rate_plans', 'id')->where('is_active', true),
            ],
            'rooms' => ['required', 'integer', 'min:1', 'max:10'],
            'adults' => ['required', 'integer', 'min:1', 'max:30'],
            'children' => ['required', 'integer', 'min:0', 'max:30'],
            'special_request' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $reservation = $reservations->createFrontDeskBooking($data);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['front_desk_booking' => $exception->getMessage()]);
        }

        $messages->sendBookingConfirmation($reservation);

        $targetTab = $reservation->check_in_date->isToday() ? 'arrivals' : 'reservations';

        return redirect()
            ->to(route('admin.front-desk', ['tab' => $targetTab]).'#reservation-'.$reservation->id)
            ->with(
                'status',
                "Booking {$reservation->booking_number} created successfully. Total ₹"
                .number_format((float) $reservation->total, 2)
                .'. Customer confirmation processed.'
            );
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

    public function checkOut(
        Stay $stay,
        FrontDeskService $service,
        CustomerMessageService $messages
    ): RedirectResponse {
        try {
            $result = $service->checkOut($stay);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['front_desk' => $exception->getMessage()]);
        }

        $reservation = Reservation::query()
            ->with(['guestLinks.guest', 'feedback'])
            ->findOrFail($stay->reservation_id);
        $messages->sendCheckout($reservation, $result['invoice']);

        return redirect()
            ->route('admin.invoices.show', $result['invoice'])
            ->with('status', 'Checkout completed. Customer invoice and feedback links queued for delivery.');
    }

    public function cancel(
        Reservation $reservation,
        ReservationLifecycleService $service,
        CustomerMessageService $messages
    ): RedirectResponse {
        try {
            $reservation = $service->cancel($reservation);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['front_desk' => $exception->getMessage()]);
        }

        $messages->sendCancellation($reservation->load(['guestLinks.guest']));

        return back()->with(
            'status',
            'Reservation cancelled. Customer notification sent. Review any collected payment for refund.'
        );
    }

    public function noShow(
        Reservation $reservation,
        ReservationLifecycleService $service,
        CustomerMessageService $messages
    ): RedirectResponse {
        try {
            $reservation = $service->markNoShow($reservation);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['front_desk' => $exception->getMessage()]);
        }

        $messages->sendNoShow($reservation->load(['guestLinks.guest']));

        return back()->with('status', 'Reservation marked no-show. Customer notification sent.');
    }

    private function roomBlockedForRange(
        Collection $blocks,
        int $roomId,
        mixed $startsOn,
        mixed $endsOn
    ): bool {
        $start = CarbonImmutable::parse($startsOn);
        $end = CarbonImmutable::parse($endsOn);

        return $blocks->contains(
            fn (RoomBlock $block) => (int) $block->room_id === $roomId
                && CarbonImmutable::parse($block->starts_on)->lt($end)
                && CarbonImmutable::parse($block->ends_on)->gt($start)
        );
    }
}
