<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchAvailabilityRequest;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use App\Services\ReservationHoldService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;

class AvailabilityController extends Controller
{
    public function index(
        Request $request,
        AvailabilityService $availabilityService,
        PricingService $pricingService,
        ReservationHoldService $holdService
    ): View|RedirectResponse {
        $roomTypes = RoomType::query()->where('is_active', true)->orderBy('name')->get();
        $ratePlans = RatePlan::query()->where('is_active', true)->orderBy('name')->get();

        $hasWebsiteHandoff = $request->filled('check_in')
            || $request->filled('check_out')
            || $request->filled('room_code');

        if ($hasWebsiteHandoff) {
            $validator = Validator::make($request->query(), [
                'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
                'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
                'adults' => ['required', 'integer', 'min:1', 'max:30'],
                'children' => ['nullable', 'integer', 'min:0', 'max:30'],
                'room_code' => ['nullable', 'string', 'max:40'],
            ]);

            if ($validator->fails()) {
                return redirect()->route('booking.search')->withErrors($validator);
            }

            $data = $validator->validated();
            $checkIn = CarbonImmutable::parse($data['check_in']);
            $checkOut = CarbonImmutable::parse($data['check_out']);
            $adults = (int) $data['adults'];
            $children = (int) ($data['children'] ?? 0);
            $roomCode = strtolower(trim((string) ($data['room_code'] ?? '')));

            $requestedRoom = $roomCode !== ''
                ? $roomTypes->firstWhere('code', $roomCode)
                : null;

            if ($roomCode !== '' && $requestedRoom === null) {
                return $this->alternativesView(
                    null,
                    $this->offersForRooms(
                        $roomTypes,
                        $ratePlans,
                        $availabilityService,
                        $pricingService,
                        $checkIn,
                        $checkOut,
                        $adults,
                        $children
                    ),
                    $checkIn,
                    $checkOut,
                    $adults,
                    $children,
                    'The selected room category is not available. Please choose another room.'
                );
            }

            $candidateRooms = $requestedRoom !== null
                ? collect([$requestedRoom])
                : $roomTypes;

            $offers = $this->offersForRooms(
                $candidateRooms,
                $ratePlans,
                $availabilityService,
                $pricingService,
                $checkIn,
                $checkOut,
                $adults,
                $children
            );

            foreach ($offers as $offer) {
                try {
                    $hold = $holdService->create(
                        $offer['room_type']->id,
                        $offer['rate_plan']->id,
                        $checkIn,
                        $checkOut,
                        $offer['rooms'],
                        $adults,
                        $children
                    );

                    return redirect()->route('booking.guest', ['token' => $hold->token]);
                } catch (RuntimeException) {
                    // Availability changed between the quote and the atomic hold.
                    // Rebuild alternatives below instead of asking the guest to re-enter dates.
                }
            }

            $alternativeRooms = $requestedRoom !== null
                ? $roomTypes->where('id', '!=', $requestedRoom->id)->values()
                : $roomTypes;

            $alternatives = $this->offersForRooms(
                $alternativeRooms,
                $ratePlans,
                $availabilityService,
                $pricingService,
                $checkIn,
                $checkOut,
                $adults,
                $children
            );

            $message = $requestedRoom !== null
                ? $requestedRoom->name.' is no longer available for your selected dates. Please choose another room.'
                : 'No room is currently available for your selected dates. Please try another room or different dates.';

            return $this->alternativesView(
                $requestedRoom,
                $alternatives,
                $checkIn,
                $checkOut,
                $adults,
                $children,
                $message
            );
        }

        $adults = max(1, min(30, (int) $request->query('adults', 2)));

        return view('booking.search', [
            'roomTypes' => $roomTypes,
            'ratePlans' => $ratePlans,
            'availability' => null,
            'quote' => null,
            'quoteError' => null,
            'search' => [
                'check_in' => null,
                'check_out' => null,
                'room_type_id' => null,
                'rate_plan_id' => $ratePlans->count() === 1 ? $ratePlans->first()->id : null,
                'rooms' => 1,
                'adults' => $adults,
                'children' => 0,
            ],
        ]);
    }

    public function search(
        SearchAvailabilityRequest $request,
        AvailabilityService $availabilityService,
        PricingService $pricingService
    ): View {
        $data = $request->validated();
        $roomType = RoomType::query()->where('is_active', true)->findOrFail($data['room_type_id']);
        $ratePlan = RatePlan::query()->where('is_active', true)->findOrFail($data['rate_plan_id']);
        $checkIn = CarbonImmutable::parse($data['check_in']);
        $checkOut = CarbonImmutable::parse($data['check_out']);
        $rooms = (int) ($data['rooms'] ?? 1);

        $quote = null;
        $quoteError = null;
        $adults = (int) $data['adults'];
        $children = (int) ($data['children'] ?? 0);

        if ($roomType->max_adults !== null && $adults > ($roomType->max_adults * $rooms)) {
            $quoteError = 'The selected room quantity cannot accommodate this many adults.';
        } elseif (
            $roomType->max_children !== null
            && $children > ($roomType->max_children * $rooms)
        ) {
            $quoteError = 'The selected room quantity cannot accommodate this many children.';
        }

        if ($quoteError === null) {
            try {
                $quote = $pricingService->quote(
                    $roomType->id,
                    $ratePlan->id,
                    $checkIn,
                    $checkOut,
                    $rooms
                );
            } catch (RuntimeException $exception) {
                $quoteError = $exception->getMessage();
            }
        }

        return view('booking.search', [
            'roomTypes' => RoomType::query()->where('is_active', true)->orderBy('name')->get(),
            'ratePlans' => RatePlan::query()->where('is_active', true)->orderBy('name')->get(),
            'availability' => $availabilityService->forRoomType($roomType->id, $checkIn, $checkOut),
            'selectedRoomType' => $roomType,
            'selectedRatePlan' => $ratePlan,
            'search' => $data,
            'quote' => $quote,
            'quoteError' => $quoteError,
        ]);
    }

    private function offersForRooms(
        $roomTypes,
        $ratePlans,
        AvailabilityService $availabilityService,
        PricingService $pricingService,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $adults,
        int $children
    ): array {
        $offers = [];

        foreach ($roomTypes as $roomType) {
            $rooms = $this->requiredRooms($roomType, $adults, $children);
            if ($rooms === null) {
                continue;
            }

            $availability = $availabilityService->forRoomType($roomType->id, $checkIn, $checkOut);
            if ($availability['available_rooms'] < $rooms) {
                continue;
            }

            $best = null;
            foreach ($ratePlans as $ratePlan) {
                try {
                    $quote = $pricingService->quote(
                        $roomType->id,
                        $ratePlan->id,
                        $checkIn,
                        $checkOut,
                        $rooms
                    );
                } catch (RuntimeException) {
                    continue;
                }

                if ($best === null || $quote['total'] < $best['quote']['total']) {
                    $best = [
                        'room_type' => $roomType,
                        'rate_plan' => $ratePlan,
                        'rooms' => $rooms,
                        'availability' => $availability,
                        'quote' => $quote,
                    ];
                }
            }

            if ($best !== null) {
                $offers[] = $best;
            }
        }

        usort($offers, function (array $left, array $right): int {
            $totalComparison = $left['quote']['total'] <=> $right['quote']['total'];

            return $totalComparison !== 0
                ? $totalComparison
                : ($left['room_type']->id <=> $right['room_type']->id);
        });

        return $offers;
    }

    private function requiredRooms(RoomType $roomType, int $adults, int $children): ?int
    {
        $adultCapacity = (int) ($roomType->max_adults ?? 0);
        $childCapacity = (int) ($roomType->max_children ?? 0);

        $roomsForAdults = $adultCapacity > 0 ? (int) ceil($adults / $adultCapacity) : 1;
        $roomsForChildren = $children > 0 && $childCapacity > 0
            ? (int) ceil($children / $childCapacity)
            : 1;

        if ($children > 0 && $roomType->max_children !== null && $childCapacity === 0) {
            return null;
        }

        $rooms = max(1, $roomsForAdults, $roomsForChildren);

        return $rooms <= 10 ? $rooms : null;
    }

    private function alternativesView(
        ?RoomType $requestedRoom,
        array $alternatives,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $adults,
        int $children,
        string $message
    ): View {
        return view('booking.alternatives', [
            'requestedRoom' => $requestedRoom,
            'alternatives' => $alternatives,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'adults' => $adults,
            'children' => $children,
            'message' => $message,
        ]);
    }
}
