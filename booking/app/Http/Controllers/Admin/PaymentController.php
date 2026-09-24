<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Refund;
use App\Models\RestaurantOrder;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $role = (string) ($request->attributes->get('admin_user')?->role ?? '');
        $showHotelPayments = in_array($role, ['administrator', 'front_desk', 'accounts'], true);
        $showRestaurantPayments = in_array($role, ['administrator', 'accounts', 'restaurant'], true);
        $selectedReservationId = max(0, (int) $request->query('reservation_id', 0));
        $selectedFolioId = max(0, (int) $request->query('folio_id', 0));

        $payments = Payment::query()
            ->with(['refunds', 'reservation', 'folio', 'restaurantOrder'])
            ->when(
                $role === 'restaurant',
                fn ($query) => $query->whereHas(
                    'restaurantOrder',
                    fn ($orderQuery) => $orderQuery->whereIn('order_type', ['dine_in', 'takeaway'])
                )
            )
            ->when(
                $role === 'front_desk',
                fn ($query) => $query->whereNull('restaurant_order_id')
            )
            ->latest('id')
            ->limit(100)
            ->get();

        return view('admin.payments', [
            'showHotelPayments' => $showHotelPayments,
            'showRestaurantPayments' => $showRestaurantPayments,
            'canRefund' => in_array($role, ['administrator', 'front_desk', 'accounts'], true),
            'reservations' => $showHotelPayments
                ? Reservation::query()
                    ->where('status', 'confirmed')
                    ->when($selectedReservationId > 0, fn ($query) => $query->whereKey($selectedReservationId))
                    ->orderBy('check_in_date')
                    ->limit(50)
                    ->get()
                : collect(),
            'folios' => $showHotelPayments
                ? Folio::query()
                    ->where('status', 'open')
                    ->where('balance', '>', 0)
                    ->when($selectedFolioId > 0, fn ($query) => $query->whereKey($selectedFolioId))
                    ->orderByDesc('id')
                    ->get()
                : collect(),
            'restaurantOrders' => $showRestaurantPayments
                ? RestaurantOrder::query()
                    ->whereIn('order_type', ['dine_in', 'takeaway'])
                    ->where('status', 'served')
                    ->whereIn('payment_status', ['unpaid', 'partially_paid'])
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get()
                : collect(),
            'invoices' => $showHotelPayments
                ? Invoice::query()->latest('id')->limit(50)->get()
                : collect(),
            'payments' => $payments,
            'selectedReservationId' => $selectedReservationId,
            'selectedFolioId' => $selectedFolioId,
        ]);
    }

    public function store(Request $request, PaymentService $service): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'target_type' => ['required', 'in:reservation,folio,restaurant_order'],
            'target_id' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:cash,upi,card,bank_transfer'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'external_reference' => ['nullable', 'string', 'max:190'],
        ]);

        $role = (string) ($request->attributes->get('admin_user')?->role ?? '');

        if ($role === 'front_desk' && $data['target_type'] === 'restaurant_order') {
            abort(403);
        }

        if ($role === 'restaurant') {
            if ($data['target_type'] !== 'restaurant_order') {
                abort(403);
            }

            $order = RestaurantOrder::query()->findOrFail($data['target_id']);

            if (! in_array($order->order_type, ['dine_in', 'takeaway'], true)) {
                abort(403);
            }
        }

        $payload = [
            'idempotency_key' => $data['idempotency_key'],
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
        $this->assertFrontDeskHotelPaymentScope($request, $payment);

        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $refund = $service->refund($payment, [
                'idempotency_key' => $data['idempotency_key'],
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

    public function reconcileRefund(
        Request $request,
        Refund $refund,
        PaymentService $service
    ): RedirectResponse {
        $refund->loadMissing('payment');
        $this->assertFrontDeskHotelPaymentScope($request, $refund->payment);

        try {
            $refund = $service->reconcilePendingOnlineRefund($refund);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with(
            'status',
            $refund->status === 'pending'
                ? 'Online refund is still pending provider processing.'
                : ($refund->status === 'succeeded'
                    ? 'Online refund reconciled successfully.'
                    : 'Online refund reconciliation completed with status: '.$refund->status.'.')
        );
    }

    private function assertFrontDeskHotelPaymentScope(
        Request $request,
        ?Payment $payment
    ): void {
        $role = (string) ($request->attributes->get('admin_user')?->role ?? '');

        if ($role === 'front_desk' && $payment?->restaurant_order_id !== null) {
            abort(403);
        }
    }
}
