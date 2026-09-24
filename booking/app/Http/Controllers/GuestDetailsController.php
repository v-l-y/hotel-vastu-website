<?php

namespace App\Http\Controllers;

use App\Models\ReservationHold;
use App\Services\PricingService;
use App\Services\PromotionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

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

    public function previewPromo(
        string $token,
        Request $request,
        PricingService $pricingService,
        PromotionService $promotions
    ): JsonResponse {
        $data = $request->validate([
            'promo_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $hold = ReservationHold::query()
            ->active()
            ->where('token', $token)
            ->firstOrFail();

        try {
            $quote = $pricingService->quote(
                $hold->room_type_id,
                $hold->rate_plan_id,
                CarbonImmutable::parse($hold->check_in_date),
                CarbonImmutable::parse($hold->check_out_date),
                $hold->quantity
            );

            $preview = $promotions->previewQuote(
                $data['promo_code'],
                (float) $quote['subtotal'],
                (float) $quote['tax']
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($preview);
    }
}
