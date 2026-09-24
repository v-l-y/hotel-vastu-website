<?php

namespace Tests\Feature;

use App\Mail\CustomerMessageMail;
use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\Stay;
use App\Services\CustomerMessageService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfficialBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_gst_invoice_snapshots_identity_tax_components_and_print_ui(): void
    {
        config([
            'billing.legal_name' => 'Hotel Vastu Premium',
            'billing.address' => 'Test legal billing address, Bihar',
            'billing.state' => 'Bihar',
            'billing.state_code' => '10',
            'billing.gstin' => '10ABCDE1234F1Z5',
            'billing.phone' => '9000000000',
            'billing.email' => 'billing@example.com',
        ]);

        $reservation = Reservation::query()->create([
            'booking_number' => 'HV-BILL-1',
            'public_token' => (string) Str::uuid(),
            'check_in_date' => today()->subDay(),
            'check_out_date' => today(),
            'status' => 'checked_out',
            'pricing_status' => 'priced',
            'payment_status' => 'paid',
            'subtotal' => 1000,
            'tax' => 50,
            'total' => 1050,
        ]);
        $guest = Guest::query()->create([
            'first_name' => 'Billing',
            'last_name' => 'Guest',
            'phone' => '9000000001',
            'email' => 'guest@example.com',
            'gstin' => '10ABCDE1234F1Z5',
            'billing_address' => 'Customer billing address, Bihar',
            'billing_state' => 'Bihar',
            'billing_state_code' => '10',
        ]);
        ReservationGuest::query()->create([
            'reservation_id' => $reservation->id,
            'guest_id' => $guest->id,
            'role' => 'primary',
        ]);
        $stay = Stay::query()->create([
            'reservation_id' => $reservation->id,
            'status' => 'checked_out',
            'checked_in_at' => now()->subDay(),
            'checked_out_at' => now(),
        ]);
        $folio = Folio::query()->create([
            'stay_id' => $stay->id,
            'reservation_id' => $reservation->id,
            'status' => 'closed',
            'charges_total' => 1050,
            'payments_total' => 1050,
            'refunds_total' => 0,
            'balance' => 0,
        ]);
        FolioCharge::query()->create([
            'folio_id' => $folio->id,
            'category' => 'room',
            'description' => 'Accommodation',
            'quantity' => 1,
            'subtotal' => 1000,
            'tax' => 50,
            'amount' => 1050,
            'source_key' => 'billing-test-room',
        ]);
        Payment::query()->create([
            'idempotency_key' => (string) Str::uuid(),
            'folio_id' => $folio->id,
            'method' => 'upi',
            'status' => 'succeeded',
            'amount' => 1050,
            'external_reference' => 'billing-test-upi',
            'paid_at' => now(),
        ]);

        $invoice = app(InvoiceService::class)->createFromFolio($folio->fresh());

        $this->assertTrue($invoice->is_gst_invoice);
        $this->assertMatchesRegularExpression('/^HV\/\d{2}-\d{2}\/\d{6}$/', $invoice->invoice_number);
        $this->assertSame('25.00', $invoice->cgst);
        $this->assertSame('25.00', $invoice->sgst);
        $this->assertSame('0.00', $invoice->igst);
        $this->assertSame('9963', $invoice->items->first()->sac_code);
        $this->assertSame('10ABCDE1234F1Z5', $invoice->supplier_gstin);
        $this->assertSame('Customer billing address, Bihar', $invoice->recipient_address);

        $this->get(route('billing.invoice', ['token' => $invoice->public_token]))
            ->assertOk()
            ->assertSee('TAX INVOICE')
            ->assertSee('Print / Save PDF')
            ->assertSee($invoice->invoice_number)
            ->assertSee('CGST')
            ->assertSee('SGST');
    }

    public function test_unregistered_supplier_document_is_not_falsely_labelled_gst_tax_invoice(): void
    {
        config([
            'billing.gstin' => null,
            'billing.address' => null,
            'billing.state' => null,
            'billing.state_code' => null,
        ]);

        [$folio] = $this->hotelFolio('HV-NONGST-1', 500, 0);

        $invoice = app(InvoiceService::class)->createFromFolio($folio);

        $this->assertFalse($invoice->is_gst_invoice);

        $this->get(route('billing.invoice', ['token' => $invoice->public_token]))
            ->assertOk()
            ->assertSee('INVOICE / RECEIPT')
            ->assertDontSee('TAX INVOICE');
    }

    public function test_paid_restaurant_order_gets_printable_invoice_and_email_delivery(): void
    {
        Mail::fake();
        config([
            'billing.gstin' => null,
            'billing.address' => null,
            'billing.state' => null,
            'billing.state_code' => null,
        ]);

        $category = RestaurantCategory::query()->create([
            'name' => 'Main',
            'is_active' => true,
        ]);
        $menu = RestaurantMenuItem::query()->create([
            'restaurant_category_id' => $category->id,
            'name' => 'Dinner',
            'price' => 500,
            'is_active' => true,
        ]);
        $order = RestaurantOrder::query()->create([
            'order_number' => 'RO-BILL-1',
            'idempotency_key' => (string) Str::uuid(),
            'order_type' => 'takeaway',
            'guest_name' => 'Restaurant Guest',
            'guest_phone' => '9000000002',
            'guest_email' => 'restaurant@example.com',
            'status' => 'served',
            'payment_status' => 'paid',
            'subtotal' => 500,
            'tax' => 25,
            'total' => 525,
        ]);
        RestaurantOrderItem::query()->create([
            'restaurant_order_id' => $order->id,
            'restaurant_menu_item_id' => $menu->id,
            'item_name' => 'Dinner',
            'quantity' => 1,
            'unit_price' => 500,
            'line_total' => 500,
        ]);
        Payment::query()->create([
            'idempotency_key' => (string) Str::uuid(),
            'restaurant_order_id' => $order->id,
            'method' => 'cash',
            'status' => 'succeeded',
            'amount' => 525,
            'paid_at' => now(),
        ]);

        $invoice = app(InvoiceService::class)->createFromRestaurantOrder($order);
        app(CustomerMessageService::class)->sendRestaurantInvoice($invoice);

        $this->assertMatchesRegularExpression('/^HR\/\d{2}-\d{2}\/\d{6}$/', $invoice->invoice_number);
        $this->assertSame($order->id, $invoice->restaurant_order_id);
        $this->assertNotNull($invoice->fresh()->last_sent_at);

        Mail::assertSent(CustomerMessageMail::class, function (CustomerMessageMail $mail) use ($invoice) {
            return str_contains($mail->messageSubject, 'Restaurant bill '.$invoice->invoice_number)
                && str_contains($mail->messageBody, 'View / print bill:')
                && str_contains($mail->messageBody, $invoice->invoice_number);
        });
    }

    private function hotelFolio(string $bookingNumber, float $subtotal, float $tax): array
    {
        $total = $subtotal + $tax;
        $reservation = Reservation::query()->create([
            'booking_number' => $bookingNumber,
            'public_token' => (string) Str::uuid(),
            'check_in_date' => today()->subDay(),
            'check_out_date' => today(),
            'status' => 'checked_out',
            'pricing_status' => 'priced',
            'payment_status' => 'paid',
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ]);
        $guest = Guest::query()->create([
            'first_name' => 'Guest',
            'phone' => '9000000099',
        ]);
        ReservationGuest::query()->create([
            'reservation_id' => $reservation->id,
            'guest_id' => $guest->id,
            'role' => 'primary',
        ]);
        $stay = Stay::query()->create([
            'reservation_id' => $reservation->id,
            'status' => 'checked_out',
            'checked_in_at' => now()->subDay(),
            'checked_out_at' => now(),
        ]);
        $folio = Folio::query()->create([
            'stay_id' => $stay->id,
            'reservation_id' => $reservation->id,
            'status' => 'closed',
            'charges_total' => $total,
            'payments_total' => $total,
            'refunds_total' => 0,
            'balance' => 0,
        ]);
        FolioCharge::query()->create([
            'folio_id' => $folio->id,
            'category' => 'room',
            'description' => 'Accommodation',
            'quantity' => 1,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'amount' => $total,
            'source_key' => 'billing-test-'.$bookingNumber,
        ]);

        return [$folio, $reservation];
    }
}
