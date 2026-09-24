<?php

namespace App\Services;

use App\Models\Reservation;

class PreArrivalReminderService
{
    public function __construct(private CustomerMessageService $messages)
    {
    }

    public function sendForTomorrow(): int
    {
        $sent = 0;

        Reservation::query()
            ->where('status', 'confirmed')
            ->whereDate('check_in_date', today()->addDay()->toDateString())
            ->whereNull('pre_arrival_reminder_sent_at')
            ->with('guestLinks.guest')
            ->orderBy('id')
            ->chunkById(100, function ($reservations) use (&$sent) {
                foreach ($reservations as $reservation) {
                    $claimed = Reservation::query()
                        ->whereKey($reservation->id)
                        ->whereNull('pre_arrival_reminder_sent_at')
                        ->update(['pre_arrival_reminder_sent_at' => now()]);

                    if ($claimed !== 1) {
                        continue;
                    }

                    $this->messages->sendPreArrivalReminder($reservation->fresh(['guestLinks.guest']));
                    $sent++;
                }
            });

        return $sent;
    }
}
