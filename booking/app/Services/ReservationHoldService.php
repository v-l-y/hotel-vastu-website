<?php

namespace App\Services;

use App\Models\ReservationHold;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ReservationHoldService
{
    public function __construct(private AvailabilityService $availability)
    {
    }

    public function create(
        int $roomTypeId,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $quantity
    ): ReservationHold {
        return DB::transaction(function () use ($roomTypeId, $checkIn, $checkOut, $quantity) {
            RoomType::query()
                ->whereKey($roomTypeId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $availability = $this->availability->forRoomType($roomTypeId, $checkIn, $checkOut);

            if ($availability['available_rooms'] < $quantity) {
                throw new RuntimeException('Requested room inventory is no longer available.');
            }

            return ReservationHold::query()->create([
                'token' => (string) Str::uuid(),
                'room_type_id' => $roomTypeId,
                'check_in_date' => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'quantity' => $quantity,
                'expires_at' => now()->addMinutes(10),
            ]);
        }, 3);
    }
}
