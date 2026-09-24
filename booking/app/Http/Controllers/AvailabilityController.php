<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchAvailabilityRequest;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function index(): View
    {
        return view('booking.search', [
            'roomTypes' => RoomType::query()->where('is_active', true)->orderBy('name')->get(),
            'availability' => null,
        ]);
    }

    public function search(
        SearchAvailabilityRequest $request,
        AvailabilityService $availabilityService
    ): View {
        $data = $request->validated();
        $roomType = RoomType::query()->findOrFail($data['room_type_id']);

        return view('booking.search', [
            'roomTypes' => RoomType::query()->where('is_active', true)->orderBy('name')->get(),
            'availability' => $availabilityService->forRoomType(
                $roomType->id,
                CarbonImmutable::parse($data['check_in']),
                CarbonImmutable::parse($data['check_out'])
            ),
            'selectedRoomType' => $roomType,
            'search' => $data,
        ]);
    }
}
