<?php

namespace App\Http\Controllers;

use App\Models\ReservationHold;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class GuestDetailsController extends Controller
{
    public function show(string $token, PricingService $pricingService): View
    {
        $hold = ReservationHold::query()
            ->active()
            ->where('token', $token)
            ->with(['roomType', 'ratePlan'])
            ->firstOrFail();

        $quote = $pricingService->quote(
            $hold->room_type_id,
            $hold->rate_plan_id,
            CarbonImmutable::parse($hold->check_in_date),
            CarbonImmutable::parse($hold->check_out_date),
            $hold->quantity
        );

        return view('booking.guest', [
            'hold' => $hold,
            'quote' => $quote,
        ]);
    }
}
