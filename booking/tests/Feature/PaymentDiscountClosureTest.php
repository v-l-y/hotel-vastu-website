<?php

namespace Tests\Feature;

use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\PaymentGatewayOrder;
use App\Models\PromotionCode;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Services\PaymentService;
use App\Services\PricingService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentDiscountClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_payment_stales_old_gateway_order_and_exposes_exact_due(): void
    {
        $reservation = $this->reservation('HV-PAY-CLOSE-1', 1000);

        $order = PaymentGatewayOrder::query()->create([
            'provider' => 'razorpay',
            'reservation_id' => $reservation->id,
            'provider_order_id' => 'order_old_due',
            'amount_subunits' => 100000,
            'currency' => 'INR',
            'status' => 'created',
        ]);

        $service = app(PaymentService::class);
        $service->record([
            'idempotency_key' => '11111111-2222-4333-8444-555555555551',
            'reservation_id' => $reservation->id,
            'method' => 'cash',
            'amount' => 250,
        ]);

        $this->assertSame('stale', $order->fresh()->status);
        $this->assertSame('partially_paid', $reservation->fresh()->payment_status);
        $this->assertSame(750.0, $service->reservationOutstanding($reservation->id));
    }

    public function test_upi_refund_is_pending_until_external_reference_is_confirmed(): void
    {
        $reservation = $this->reservation('HV-PAY-CLOSE-2', 1000);
        $service = app(PaymentService::class);

        $payment = $service->record([
            'idempotency_key' => '11111111-2222-4333-8444-555555555552',
            'reservation_id' => $reservation->id,
            'method' => 'upi',
            'amount' => 500,
            'external_reference' => 'upi_collect_1',
        ]);

        $refund = $service->refund($payment, [
            'idempotency_key' => '11111111-2222-4333-8444-555555555553',
            'amount' => 100,
            'reason' => 'Guest adjustment',
        ]);

        $this->assertSame('pending_manual', $refund->status);
        $this->assertSame(500.0, $service->reservationNetPaid($reservation->id));

        $confirmed = $service->confirmManualRefund($refund, 'upi_refund_1');

        $this->assertSame('succeeded', $confirmed->status);
        $this->assertSame('upi_refund_1', $confirmed->external_reference);
        $this->assertSame(400.0, $service->reservationNetPaid($reservation->id));
    }

    public function test_refund_reason_is_required_by_service_boundary(): void
    {
        $reservation = $this->reservation('HV-PAY-CLOSE-3', 1000);
        $service = app(PaymentService::class);
        $payment = $service->record([
            'idempotency_key' => '11111111-2222-4333-8444-555555555554',
            'reservation_id' => $reservation->id,
            'method' => 'cash',
            'amount' => 500,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refund reason is required.');

        $service->refund($payment, [
            'idempotency_key' => '11111111-2222-4333-8444-555555555555',
            'amount' => 100,
        ]);
    }

    public function test_promo_code_is_snapshotted_and_consumed_on_booking(): void
    {
        [$type, $plan] = $this->roomFixture('promo');

        $promo = PromotionCode::query()->create([
            'code' => 'SAVE10',
            'name' => 'Save ten percent',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_subtotal' => 500,
            'usage_limit' => 5,
            'times_used' => 0,
            'is_active' => true,
        ]);

        $reservation = app(ReservationService::class)->createFrontDeskBooking([
            'first_name' => 'Promo',
            'last_name' => 'Guest',
            'phone' => '9000000001',
            'email' => 'promo@example.com',
            'check_in' => today()->addDay()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'rooms' => 1,
            'adults' => 1,
            'children' => 0,
            'promo_code' => 'save10',
        ]);

        $this->assertSame('SAVE10', $reservation->promotion_code_snapshot);
        $this->assertSame('promo', $reservation->discount_source);
        $this->assertSame('100.00', $reservation->discount);
        $this->assertSame('900.00', $reservation->total);
        $this->assertSame(1, $promo->fresh()->times_used);
    }

    public function test_manual_discount_can_create_visible_overpayment_without_losing_money(): void
    {
        [$type, $plan] = $this->roomFixture('manual');

        $reservation = app(ReservationService::class)->createFrontDeskBooking([
            'first_name' => 'Paid',
            'phone' => '9000000002',
            'check_in' => today()->addDay()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'rooms' => 1,
            'adults' => 1,
            'children' => 0,
        ]);

        $payments = app(PaymentService::class);
        $payments->record([
            'idempotency_key' => '11111111-2222-4333-8444-555555555556',
            'reservation_id' => $reservation->id,
            'method' => 'cash',
            'amount' => 1000,
        ]);

        $discounted = app(PricingService::class)->applyManualDiscount(
            $reservation->fresh(),
            'fixed',
            200,
            'Manager service recovery',
            null
        );
        $discounted = $payments->syncReservationPaymentState($discounted, true);

        $this->assertSame('800.00', $discounted->total);
        $this->assertSame('overpaid', $discounted->payment_status);
        $this->assertSame(200.0, $payments->reservationOverpaid($discounted->id));
    }

    public function test_late_provider_capture_posts_to_open_in_house_folio(): void
    {
        $reservation = $this->reservation('HV-PAY-CLOSE-4', 1000);
        $reservation->update(['status' => 'checked_in']);

        $stay = Stay::query()->create([
            'reservation_id' => $reservation->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
        $folio = Folio::query()->create([
            'stay_id' => $stay->id,
            'reservation_id' => $reservation->id,
            'status' => 'open',
        ]);
        FolioCharge::query()->create([
            'folio_id' => $folio->id,
            'category' => 'room',
            'description' => 'Room charge',
            'quantity' => 1,
            'subtotal' => 1000,
            'tax' => 0,
            'amount' => 1000,
            'source_key' => 'late-capture-test',
        ]);
        app(\App\Services\FolioService::class)->recalculate($folio);

        $payment = app(PaymentService::class)->recordProviderCaptured(
            $reservation->id,
            200,
            'pay_late_capture_1',
            '11111111-2222-4333-8444-555555555557'
        );

        $this->assertNull($payment->reservation_id);
        $this->assertSame($folio->id, $payment->folio_id);
        $this->assertSame('800.00', $folio->fresh()->balance);
    }

    private function reservation(string $number, float $total): Reservation
    {
        return Reservation::query()->create([
            'booking_number' => $number,
            'public_token' => (string) \Illuminate\Support\Str::uuid(),
            'check_in_date' => today()->addDay(),
            'check_out_date' => today()->addDays(2),
            'status' => 'confirmed',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => $total,
            'tax' => 0,
            'discount' => 0,
            'total' => $total,
        ]);
    }

    private function roomFixture(string $suffix): array
    {
        $type = RoomType::query()->create([
            'code' => 'room-'.$suffix,
            'name' => 'Room '.ucfirst($suffix),
            'base_rate' => 1000,
            'max_adults' => 2,
            'max_children' => 2,
            'is_active' => true,
        ]);
        Room::query()->create([
            'room_type_id' => $type->id,
            'number' => strtoupper(substr($suffix, 0, 1)).'101',
            'status' => 'active',
            'housekeeping_status' => 'clean',
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'plan-'.$suffix,
            'name' => 'Plan '.ucfirst($suffix),
            'is_active' => true,
        ]);

        return [$type, $plan];
    }
}
