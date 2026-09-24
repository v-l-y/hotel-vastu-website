<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentController extends Controller
{
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
            $service->refund($payment, [
                'idempotency_key' => (string) Str::uuid(),
                'amount' => $data['amount'],
                'reason' => $data['reason'] ?? null,
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with('status', 'Refund recorded.');
    }
}
