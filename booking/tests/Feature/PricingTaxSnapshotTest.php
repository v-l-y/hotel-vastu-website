<?php

namespace Tests\Feature;

use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\ReservationNightRate;
use App\Models\RoomType;
use App\Models\TaxRule;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTaxSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_is_snapshotted_per_night_across_tax_boundary(): void
    {
        $type = RoomType::query()->create([
            'code' => 'classic-tax',
            'name' => 'Classic Room',
            'base_rate' => 1000,
            'is_active' => true,
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'standard-tax',
            'name' => 'Standard',
            'is_active' => true,
        ]);

        TaxRule::query()->create([
            'name' => 'Old tax',
            'applies_to' => 'hotel',
            'rate_percent' => 10,
            'effective_to' => '2026-10-10',
            'is_active' => true,
        ]);
        TaxRule::query()->create([
            'name' => 'New tax',
            'applies_to' => 'hotel',
            'rate_percent' => 20,
            'effective_from' => '2026-10-11',
            'is_active' => true,
        ]);

        $quote = app(PricingService::class)->quote(
            $type->id,
            $plan->id,
            CarbonImmutable::parse('2026-10-10'),
            CarbonImmutable::parse('2026-10-12'),
            1
        );

        $this->assertNull($quote['tax_rate']);
        $this->assertSame([10.0, 20.0], $quote['tax_rates']);
        $this->assertSame(300.0, $quote['tax']);
        $this->assertSame(2300.0, $quote['total']);
        $this->assertSame(100.0, $quote['nights'][0]['tax_amount']);
        $this->assertSame(200.0, $quote['nights'][1]['tax_amount']);

        $reservation = Reservation::query()->create([
            'booking_number' => 'HV-TAX-SNAPSHOT',
            'public_token' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12',
            'status' => 'confirmed',
            'pricing_status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        ReservationRoom::query()->create([
            'reservation_id' => $reservation->id,
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'quantity' => 1,
        ]);

        app(PricingService::class)->priceReservation($reservation);

        $firstNight = ReservationNightRate::query()
            ->where('reservation_id', $reservation->id)
            ->whereDate('stay_date', '2026-10-10')
            ->firstOrFail();
        $secondNight = ReservationNightRate::query()
            ->where('reservation_id', $reservation->id)
            ->whereDate('stay_date', '2026-10-11')
            ->firstOrFail();

        $this->assertSame('10.0000', $firstNight->tax_rate);
        $this->assertSame('100.00', $firstNight->tax_amount);
        $this->assertSame('1100.00', $firstNight->gross_total);
        $this->assertSame('20.0000', $secondNight->tax_rate);
        $this->assertSame('200.00', $secondNight->tax_amount);
        $this->assertSame('1200.00', $secondNight->gross_total);
    }
}
