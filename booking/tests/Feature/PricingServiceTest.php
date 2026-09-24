<?php

namespace Tests\Feature;

use App\Models\RatePlan;
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
}
