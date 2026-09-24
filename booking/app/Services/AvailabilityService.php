<?php

namespace App\Services;

use App\Models\ReservationHold;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomBlock;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class AvailabilityService
{
    public function forRoomType(int $roomTypeId, CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw new InvalidArgumentException('Check-out must be after check-in.');
        }

        $totalRooms = Room::query()
            ->where('room_type_id', $roomTypeId)
            ->where('status', 'active')
            ->count();

        $blockedRooms = RoomBlock::query()
            ->where('status', 'active')
            ->whereHas('room', fn ($query) => $query
                ->where('room_type_id', $roomTypeId)
                ->where('status', 'active'))
            ->whereDate('starts_on', '<', $checkOut->toDateString())
            ->whereDate('ends_on', '>', $checkIn->toDateString())
            ->distinct()
            ->count('room_id');

        $reservedRooms = (int) ReservationRoom::query()
            ->where('room_type_id', $roomTypeId)
            ->whereHas('reservation', function ($query) use ($checkIn, $checkOut) {
                $query
                    ->whereDate('check_in_date', '<', $checkOut->toDateString())
                    ->whereDate('check_out_date', '>', $checkIn->toDateString())
                    ->where(function ($statusQuery) {
                        $statusQuery
                            ->whereIn('status', ['confirmed', 'checked_in'])
                            ->orWhere(function ($pendingQuery) {
                                $pendingQuery
                                    ->where('status', 'pending')
                                    ->where(function ($expiryQuery) {
                                        $expiryQuery
                                            ->whereNull('expires_at')
                                            ->orWhere('expires_at', '>', now());
                                    });
                            });
                    });
            })
            ->sum('quantity');

        $heldRooms = (int) ReservationHold::query()
            ->active()
            ->where('room_type_id', $roomTypeId)
            ->whereDate('check_in_date', '<', $checkOut->toDateString())
            ->whereDate('check_out_date', '>', $checkIn->toDateString())
            ->sum('quantity');

        return [
            'total_rooms' => $totalRooms,
            'blocked_rooms' => $blockedRooms,
            'reserved_rooms' => $reservedRooms,
            'held_rooms' => $heldRooms,
            'available_rooms' => max(0, $totalRooms - $blockedRooms - $reservedRooms - $heldRooms),
        ];
    }
}
