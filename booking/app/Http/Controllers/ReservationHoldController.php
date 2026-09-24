<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateReservationHoldRequest;
use App\Models\RoomType;
use App\Services\ReservationHoldService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class ReservationHoldController extends Controller
{
    public function store(
        CreateReservationHoldRequest $request,
        ReservationHoldService $holdService
    ): RedirectResponse {
        $data = $request->validated();

        try {
            $hold = $holdService->create(
                (int) $data['room_type_id'],
                (int) $data['rate_plan_id'],
                CarbonImmutable::parse($data['check_in']),
                CarbonImmutable::parse($data['check_out']),
                (int) $data['rooms'],
                (int) $data['adults'],
                (int) ($data['children'] ?? 0)
            );
        } catch (RuntimeException $exception) {
            if ($request->boolean('website_handoff')) {
                $roomCode = RoomType::query()
                    ->whereKey((int) $data['room_type_id'])
                    ->value('code');

                return redirect()
                    ->route('booking.search', [
                        'check_in' => $data['check_in'],
                        'check_out' => $data['check_out'],
                        'adults' => $data['adults'],
                        'children' => $data['children'] ?? 0,
                        'room_code' => $roomCode,
                    ])
                    ->withErrors([
                        'availability' => 'That room is no longer available. Please choose another room.',
                    ]);
            }

            return back()->withInput()->withErrors(['availability' => $exception->getMessage()]);
        }

        return redirect()->route('booking.guest', ['token' => $hold->token]);
    }
}
