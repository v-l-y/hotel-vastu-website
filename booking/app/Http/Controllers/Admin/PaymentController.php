<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    public function index(): View
    {
        return view('admin.payments', [
            'reservations' => Reservation::query()
                ->where('status', 'confirmed')
                ->orderBy('check_in_date')
                ->limit(50)
                ->get(),
            'folios' => Folio::query()
                ->where('status', 'open')
                ->where('balance', '>', 0)
                ->orderByDesc('id')
                ->get(),
            'restaurantOrders' => RestaurantOrder::query()
                ->whereIn('order_type', ['dine_in', 'takeaway'])
                ->where('status', 'served')
                ->whereIn('payment_status', ['unpaid', 'partially_paid'])
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
            'payments' => Payment::query()
                ->with(['refunds', 'reservation', 'folio', 'restaurantOrder'])
                ->latest('id')
                ->limit(100)
                ->get(),
        ]);
    }

    public function store(Request $request, PaymentService $service): RedirectResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:reservation,folio,restaurant_order'],
            'target_id' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:cash,upi,card,bank_transfer'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'external_reference' => ['nullable', 'string', 'max:190'],
        ]);

        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'method' => $data['method'],
            'amount' => $data['amount'],
            'external_reference' => $data['external_reference'] ?? null,
            'status' => 'succeeded',
        ];
        $payload[$data['target_type'].'_id'] = $data['target_id'];

        try {
            $service->record($payload);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('status', 'Verified payment recorded.');
    }

    public function refund(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $refund = $service->refund($payment, [
                'idempotency_key' => (string) Str::uuid(),
                'amount' => $data['amount'],
                'reason' => $data['reason'] ?? null,
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with(
            'status',
            $refund->status === 'pending'
                ? 'Online refund initiated and awaiting provider processing.'
                : 'Refund completed.'
        );
    }
}
