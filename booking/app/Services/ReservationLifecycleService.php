<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReservationLifecycleService
{
    public function cancel(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($reservation->status, ['pending', 'confirmed'], true)) {
                throw new RuntimeException('Only pending or confirmed reservations can be cancelled.');
            }

            $reservation->update(['status' => 'cancelled']);

            return $reservation->fresh();
        }, 3);
    }

    public function markNoShow(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->status !== 'confirmed') {
                throw new RuntimeException('Only confirmed reservations can be marked no-show.');
            }

            if (today()->lt($reservation->check_in_date)) {
                throw new RuntimeException('A reservation cannot be marked no-show before its check-in date.');
            }

            $reservation->update(['status' => 'no_show']);

            return $reservation->fresh();
        }, 3);
    }
}
