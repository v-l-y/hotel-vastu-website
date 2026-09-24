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
    public function forRoomType(
        int $roomTypeId,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        ?int $excludeReservationId = null
    ): array {
        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw new InvalidArgumentException('Check-out must be after check-in.');
        }

        $totalRooms = Room::query()
            ->where('room_type_id', $roomTypeId)
            ->where('status', 'active')
            ->where('housekeeping_status', '!=', 'out_of_order')
            ->count();

        $committedPeak = 0;
        $blockedAtPeak = 0;
        $reservedAtPeak = 0;
        $heldAtPeak = 0;

        for ($date = $checkIn; $date->lessThan($checkOut); $date = $date->addDay()) {
            $stayDate = $date->toDateString();

            $blockedRooms = RoomBlock::query()
                ->where('status', 'active')
                ->whereHas('room', fn ($query) => $query
                    ->where('room_type_id', $roomTypeId)
                    ->where('status', 'active')
                    ->where('housekeeping_status', '!=', 'out_of_order'))
                ->whereDate('starts_on', '<=', $stayDate)
                ->whereDate('ends_on', '>', $stayDate)
                ->distinct()
                ->count('room_id');

            $reservedRooms = (int) ReservationRoom::query()
                ->where('room_type_id', $roomTypeId)
                ->whereHas('reservation', function ($query) use ($stayDate, $excludeReservationId) {
                    if ($excludeReservationId !== null) {
                        $query->where('id', '!=', $excludeReservationId);
                    }

                    $query
                        ->whereDate('check_in_date', '<=', $stayDate)
                        ->whereDate('check_out_date', '>', $stayDate)
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
                ->whereDate('check_in_date', '<=', $stayDate)
                ->whereDate('check_out_date', '>', $stayDate)
                ->sum('quantity');

            $committed = $blockedRooms + $reservedRooms + $heldRooms;
            if ($committed > $committedPeak) {
                $committedPeak = $committed;
                $blockedAtPeak = $blockedRooms;
                $reservedAtPeak = $reservedRooms;
                $heldAtPeak = $heldRooms;
            }
        }

        return [
            'total_rooms' => $totalRooms,
            'blocked_rooms' => $blockedAtPeak,
            'reserved_rooms' => $reservedAtPeak,
            'held_rooms' => $heldAtPeak,
            'available_rooms' => max(0, $totalRooms - $committedPeak),
        ];
    }
}
