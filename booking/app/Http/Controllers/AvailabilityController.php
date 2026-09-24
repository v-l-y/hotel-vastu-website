<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchAvailabilityRequest;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use RuntimeException;

class AvailabilityController extends Controller
{
    public function index(): View
    {
        return view('booking.search', [
            'roomTypes' => RoomType::query()->where('is_active', true)->orderBy('name')->get(),
            'ratePlans' => RatePlan::query()->where('is_active', true)->orderBy('name')->get(),
            'availability' => null,
            'quote' => null,
            'quoteError' => null,
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

        return view('booking.search', [
            'roomTypes' => RoomType::query()->where('is_active', true)->orderBy('name')->get(),
            'ratePlans' => RatePlan::query()->where('is_active', true)->orderBy('name')->get(),
            'availability' => $availabilityService->forRoomType(
                $roomType->id,
                $checkIn,
                $checkOut
            ),
            'selectedRoomType' => $roomType,
            'selectedRatePlan' => $ratePlan,
            'search' => $data,
            'quote' => $quote,
            'quoteError' => $quoteError,
        ]);
    }
}
