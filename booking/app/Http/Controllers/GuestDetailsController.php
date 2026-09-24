<?php

namespace App\Http\Controllers;

use App\Models\ReservationHold;
use Illuminate\View\View;

class GuestDetailsController extends Controller
{
    public function show(string $token): View
    {
        $hold = ReservationHold::query()
            ->active()
            ->where('token', $token)
            ->with('roomType')
            ->firstOrFail();

        return view('booking.guest', ['hold' => $hold]);
    }
}
