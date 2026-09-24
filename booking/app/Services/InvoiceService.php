<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RestaurantOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceService
{
    public function __construct(private BillingDocumentNumberService $numbers)
    {
    }

    public function createFromFolio(Folio $folio): Invoice
    {
        return DB::transaction(function () use ($folio) {
            $existing = Invoice::query()->where('folio_id', $folio->id)->first();
            if ($existing !== null) {
                return $existing->load(['items', 'creditNotes.items', 'creditNotes.refund']);
            }

            $folio->load([
                'charges',
                'reservation.guestLinks.guest',
            ]);

            $guest = $folio->reservation?->guestLinks?->first()?->guest;
            $recipient = [
                'name' => trim((string) (($guest?->first_name ?? '').' '.($guest?->last_name ?? ''))),
                'phone' => $guest?->phone,
                'email' => $guest?->email,
                'gstin' => $guest?->gstin,
                'address' => $guest?->billing_address,
                'state' => $guest?->billing_state,
                'state_code' => $guest?->billing_state_code,
            ];

            $profile = $this->supplierProfile();
            $this->assertRecipientCompliance($profile, $recipient, (float) $folio->charges_total);

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->numbers->next((string) config('billing.series.hotel_invoice', 'HV')),
                'public_token' => (string) Str::uuid(),
                'document_type' => 'hotel',
                'folio_id' => $folio->id,
                'restaurant_order_id' => null,
                ...$this->invoiceIdentitySnapshot($profile, $recipient),
                'subtotal' => round((float) $folio->charges->sum('subtotal'), 2),
                'tax' => round((float) $folio->charges->sum('tax'), 2),
                'cgst' => 0,
                'sgst' => 0,
                'igst' => 0,
                'total' => (float) $folio->charges_total,
                'paid' => round((float) $folio->payments_total - (float) $folio->refunds_total, 2),
                'balance' => (float) $folio->balance,
                'issued_at' => now(),
            ]);

            foreach ($folio->charges as $charge) {
                $this->createItem(
                    $invoice,
                    $charge->category,
                    $charge->description,
                    (int) $charge->quantity,
                    (float) $charge->subtotal,
                    (float) $charge->tax,
                    (float) $charge->amount,
                    $profile
                );
            }

            $this->syncTaxTotals($invoice);

            return $invoice->fresh(['items', 'creditNotes.items', 'creditNotes.refund']);
        }, 3);
    }

    public function createFromRestaurantOrder(RestaurantOrder $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            $order = RestaurantOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with(['items', 'payments.refunds'])
                ->firstOrFail();

            if ($order->order_type === 'room_service') {
                throw new RuntimeException('Room-service charges are billed on the hotel checkout invoice.');
            }

            if ($order->status !== 'served' || $order->payment_status !== 'paid') {
                throw new RuntimeException('A restaurant invoice can be issued only after the served order is fully paid.');
            }

            $existing = Invoice::query()->where('restaurant_order_id', $order->id)->first();
            if ($existing !== null) {
                return $existing->load(['items', 'creditNotes.items', 'creditNotes.refund']);
            }

            $recipient = [
                'name' => trim((string) ($order->guest_name ?? '')),
                'phone' => $order->guest_phone,
                'email' => $order->guest_email,
                'gstin' => $order->guest_gstin,
                'address' => $order->guest_billing_address,
                'state' => $order->guest_billing_state,
                'state_code' => $order->guest_billing_state_code,
            ];

            $profile = $this->supplierProfile();
            $this->assertRecipientCompliance($profile, $recipient, (float) $order->total);

            $paid = round(
                (float) $order->payments->where('status', 'succeeded')->sum('amount')
                - (float) $order->payments->flatMap->refunds->where('status', 'succeeded')->sum('amount'),
                2
            );

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->numbers->next((string) config('billing.series.restaurant_invoice', 'HR')),
                'public_token' => (string) Str::uuid(),
                'document_type' => 'restaurant',
                'folio_id' => null,
                'restaurant_order_id' => $order->id,
                ...$this->invoiceIdentitySnapshot($profile, $recipient),
                'subtotal' => (float) $order->subtotal,
                'tax' => (float) $order->tax,
                'cgst' => 0,
                'sgst' => 0,
                'igst' => 0,
                'total' => (float) $order->total,
                'paid' => $paid,
                'balance' => max(0, round((float) $order->total - $paid, 2)),
                'issued_at' => now(),
            ]);

            $taxRate = (float) $order->subtotal > 0
                ? round(((float) $order->tax / (float) $order->subtotal) * 100, 4)
                : 0.0;
            $remainingTax = round((float) $order->tax, 2);
            $count = $order->items->count();

            foreach ($order->items->values() as $index => $item) {
                $lineTax = $index === $count - 1
                    ? $remainingTax
                    : round((float) $item->line_total * ($taxRate / 100), 2);
                $remainingTax = round($remainingTax - $lineTax, 2);

                $this->createItem(
                    $invoice,
                    'restaurant',
                    $item->item_name,
                    (int) $item->quantity,
                    (float) $item->line_total,
                    $lineTax,
                    round((float) $item->line_total + $lineTax, 2),
                    $profile
                );
            }

            $this->syncTaxTotals($invoice);

            return $invoice->fresh(['items', 'creditNotes.items', 'creditNotes.refund']);
        }, 3);
    }

    private function createItem(
        Invoice $invoice,
        string $category,
        string $description,
        int $quantity,
        float $subtotal,
        float $tax,
        float $amount,
        array $profile
    ): InvoiceItem {
        $taxRate = $subtotal > 0 ? round(($tax / $subtotal) * 100, 4) : 0.0;
        $components = $this->taxComponents($tax, $taxRate, $profile);

        return InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category' => $category,
            'description' => $description,
            'sac_code' => $category === 'restaurant'
                ? config('billing.sac_restaurant', '9963')
                : config('billing.sac_accommodation', '9963'),
            'quantity' => $quantity,
            'subtotal' => round($subtotal, 2),
            'tax_rate_percent' => $taxRate,
            'tax' => round($tax, 2),
            'cgst_rate_percent' => $components['cgst_rate'],
            'cgst_amount' => $components['cgst'],
            'sgst_rate_percent' => $components['sgst_rate'],
            'sgst_amount' => $components['sgst'],
            'igst_rate_percent' => $components['igst_rate'],
            'igst_amount' => $components['igst'],
            'amount' => round($amount, 2),
        ]);
    }

    private function syncTaxTotals(Invoice $invoice): void
    {
        $invoice->update([
            'cgst' => round((float) $invoice->items()->sum('cgst_amount'), 2),
            'sgst' => round((float) $invoice->items()->sum('sgst_amount'), 2),
            'igst' => round((float) $invoice->items()->sum('igst_amount'), 2),
        ]);
    }

    private function supplierProfile(): array
    {
        $gstin = strtoupper(trim((string) config('billing.gstin', '')));

        if ($gstin !== '' && ! $this->validGstin($gstin)) {
            throw new RuntimeException('Billing GSTIN is invalid. Fix BILLING_GSTIN before issuing a tax invoice.');
        }

        $isGst = $gstin !== '';
        $stateCode = trim((string) config('billing.state_code', ''));

        if ($stateCode === '' && $isGst) {
            $stateCode = substr($gstin, 0, 2);
        }

        $profile = [
            'is_gst' => $isGst,
            'name' => trim((string) config('billing.legal_name', 'Hotel Vastu Premium')),
            'address' => trim((string) config('billing.address', '')),
            'gstin' => $gstin,
            'state' => trim((string) config('billing.state', '')),
            'state_code' => $stateCode,
            'phone' => trim((string) config('billing.phone', '')),
            'email' => trim((string) config('billing.email', '')),
        ];

        if (
            $isGst
            && (
                $profile['address'] === ''
                || $profile['state'] === ''
                || ! preg_match('/^[0-9]{2}$/', $stateCode)
            )
        ) {
            throw new RuntimeException(
                'GST billing profile is incomplete. Configure legal address, State and two-digit State code.'
            );
        }

        return $profile;
    }

    private function invoiceIdentitySnapshot(array $profile, array $recipient): array
    {
        $recipientGstin = strtoupper(trim((string) ($recipient['gstin'] ?? '')));
        $recipientStateCode = trim((string) ($recipient['state_code'] ?? ''));

        return [
            'is_gst_invoice' => $profile['is_gst'],
            'supplier_name' => $profile['name'],
            'supplier_address' => $profile['address'] !== '' ? $profile['address'] : null,
            'supplier_gstin' => $profile['gstin'] !== '' ? $profile['gstin'] : null,
            'supplier_state' => $profile['state'] !== '' ? $profile['state'] : null,
            'supplier_state_code' => $profile['state_code'] !== '' ? $profile['state_code'] : null,
            'supplier_phone' => $profile['phone'] !== '' ? $profile['phone'] : null,
            'supplier_email' => $profile['email'] !== '' ? $profile['email'] : null,
            'recipient_name' => trim((string) ($recipient['name'] ?? '')) ?: null,
            'recipient_phone' => trim((string) ($recipient['phone'] ?? '')) ?: null,
            'recipient_email' => trim((string) ($recipient['email'] ?? '')) ?: null,
            'recipient_gstin' => $recipientGstin !== '' ? $recipientGstin : null,
            'recipient_address' => trim((string) ($recipient['address'] ?? '')) ?: null,
            'recipient_state' => trim((string) ($recipient['state'] ?? '')) ?: null,
            'recipient_state_code' => $recipientStateCode !== '' ? $recipientStateCode : null,
            // Accommodation and restaurant services are supplied at the hotel premises.
            'place_of_supply' => $profile['state'] !== '' ? $profile['state'] : null,
            'place_of_supply_code' => $profile['state_code'] !== '' ? $profile['state_code'] : null,
            'reverse_charge' => false,
        ];
    }

    private function assertRecipientCompliance(array $profile, array $recipient, float $total): void
    {
        if (! $profile['is_gst']) {
            return;
        }

        $gstin = strtoupper(trim((string) ($recipient['gstin'] ?? '')));

        if ($gstin !== '' && ! $this->validGstin($gstin)) {
            throw new RuntimeException('Customer GSTIN is invalid. Correct billing details before issuing the invoice.');
        }

        $name = trim((string) ($recipient['name'] ?? ''));
        $address = trim((string) ($recipient['address'] ?? ''));
        $state = trim((string) ($recipient['state'] ?? ''));
        $stateCode = trim((string) ($recipient['state_code'] ?? ''));
        $completeRecipientIdentity =
            $name !== ''
            && $address !== ''
            && $state !== ''
            && preg_match('/^[0-9]{2}$/', $stateCode) === 1;

        if ($gstin !== '' && ! $completeRecipientIdentity) {
            throw new RuntimeException(
                'Registered customer name, billing address, State and State code are required for the GST invoice.'
            );
        }

        if ($gstin === '' && $total >= 50000 && ! $completeRecipientIdentity) {
            throw new RuntimeException(
                'Customer name, billing address, State and State code are required when an unregistered GST invoice is ₹50,000 or more.'
            );
        }
    }

    private function taxComponents(float $tax, float $taxRate, array $profile): array
    {
        if (! $profile['is_gst'] || $tax <= 0) {
            return [
                'cgst_rate' => 0,
                'cgst' => 0,
                'sgst_rate' => 0,
                'sgst' => 0,
                'igst_rate' => 0,
                'igst' => 0,
            ];
        }

        $cgst = round($tax / 2, 2);
        $sgst = round($tax - $cgst, 2);

        return [
            'cgst_rate' => round($taxRate / 2, 4),
            'cgst' => $cgst,
            'sgst_rate' => round($taxRate / 2, 4),
            'sgst' => $sgst,
            'igst_rate' => 0,
            'igst' => 0,
        ];
    }

    private function validGstin(string $gstin): bool
    {
        return preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin) === 1;
    }
}
