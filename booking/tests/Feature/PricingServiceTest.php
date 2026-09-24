<?php

namespace Tests\Feature;

use App\Models\RatePlan;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\TaxRule;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_uses_configured_base_rate_and_tax(): void
    {
        $type = RoomType::query()->create([
            'code' => 'classic',
            'name' => 'Classic Room',
            'base_rate' => 2000,
            'is_active' => true,
        ]);

        $plan = RatePlan::query()->create([
            'code' => 'room-only',
            'name' => 'Room Only',
            'is_active' => true,
        ]);

        TaxRule::query()->create([
            'name' => 'Hotel tax',
            'applies_to' => 'hotel',
            'rate_percent' => 10,
            'is_active' => true,
        ]);

        $quote = app(PricingService::class)->quote(
            $type->id,
            $plan->id,
            CarbonImmutable::parse('2026-10-10'),
            CarbonImmutable::parse('2026-10-12'),
            1
        );

        $this->assertSame(4000.0, $quote['subtotal']);
        $this->assertSame(400.0, $quote['tax']);
        $this->assertSame(4400.0, $quote['total']);
        $this->assertCount(2, $quote['nights']);
    }

    public function test_dated_rate_and_effective_tax_rules_are_snapshotted_in_quote(): void
    {
        $type = RoomType::query()->create([
            'code' => 'premium',
            'name' => 'Premium Room',
            'base_rate' => 3000,
            'is_active' => true,
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'breakfast',
            'name' => 'Breakfast Included',
            'includes_breakfast' => true,
            'is_active' => true,
        ]);

        RoomRate::query()->create([
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-12',
            'nightly_rate' => 3500,
            'min_stay' => 1,
        ]);

        TaxRule::query()->create([
            'name' => 'Current hotel tax',
            'applies_to' => 'hotel',
            'rate_percent' => 12,
            'effective_from' => '2026-10-01',
            'effective_to' => '2026-10-31',
            'is_active' => true,
        ]);
        TaxRule::query()->create([
            'name' => 'Expired hotel tax',
            'applies_to' => 'hotel',
            'rate_percent' => 99,
            'effective_to' => '2026-09-30',
            'is_active' => true,
        ]);

        $quote = app(PricingService::class)->quote(
            $type->id,
            $plan->id,
            CarbonImmutable::parse('2026-10-10'),
            CarbonImmutable::parse('2026-10-12'),
            1
        );

        $this->assertSame(7000.0, $quote['subtotal']);
        $this->assertSame(12.0, $quote['tax_rate']);
        $this->assertSame(840.0, $quote['tax']);
        $this->assertSame(7840.0, $quote['total']);
        $this->assertSame(3500.0, $quote['nights'][0]['unit_rate']);
    }
}
