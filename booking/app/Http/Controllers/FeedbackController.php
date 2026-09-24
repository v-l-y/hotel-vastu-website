<?php

namespace App\Http\Controllers;

use App\Models\ReservationFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function show(string $token): View
    {
        $feedback = ReservationFeedback::query()
            ->where('token', $token)
            ->with('reservation')
            ->firstOrFail();

        return view('booking.feedback', ['feedback' => $feedback]);
    }

    public function store(string $token, Request $request): RedirectResponse
    {
        $feedback = ReservationFeedback::query()
            ->where('token', $token)
            ->with('reservation')
            ->firstOrFail();

        if ($feedback->reservation->status !== 'checked_out') {
            return back()->withErrors(['feedback' => 'Feedback becomes available after checkout.']);
        }

        if ($feedback->submitted_at !== null) {
            return redirect()->route('booking.feedback.show', ['token' => $token]);
        }

        $data = $request->validate([
            'overall_rating' => ['required', 'integer', 'between:1,5'],
            'cleanliness_rating' => ['required', 'integer', 'between:1,5'],
            'service_rating' => ['required', 'integer', 'between:1,5'],
            'food_rating' => ['nullable', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback->update($data + ['submitted_at' => now()]);

        return redirect()->route('booking.feedback.show', ['token' => $token])
            ->with('status', 'Thank you for your feedback.');
    }
}
