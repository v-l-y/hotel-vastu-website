<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchAvailabilityRequest;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AvailabilityController extends Controller
{
    public function index(Request $request): View
    {
        $roomTypes = RoomType::query()->where('is_active', true)->orderBy('name')->get();
        $ratePlans = RatePlan::query()->where('is_active', true)->orderBy('name')->get();

        $roomCode = strtolower(trim((string) $request->query('room_code', '')));
        $prefillRoom = $roomCode !== '' ? $roomTypes->firstWhere('code', $roomCode) : null;
        $adults = max(1, min(30, (int) $request->query('adults', 2)));

        return view('booking.search', [
            'roomTypes' => $roomTypes,
            'ratePlans' => $ratePlans,
            'availability' => null,
            'quote' => null,
            'quoteError' => null,
            'search' => [
                'check_in' => $request->query('check_in'),
                'check_out' => $request->query('check_out'),
                'room_type_id' => $prefillRoom?->id,
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
}
