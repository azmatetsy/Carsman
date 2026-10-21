<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ServiceDifficultyLevel;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopAvailabilitySlot;
use App\Models\ShopService;
use App\Models\Vehicle;
use App\Services\BookingStateMachineService;
use App\Services\EstimateCalculationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;

final class EstimateAndBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_service_returns_supported_range_only_when_verified_shop_pricing_exists(): void
    {
        $vehicle = Vehicle::query()->create([
            'year' => 2022,
            'make' => 'Toyota',
            'model' => 'Camry',
            'submodel' => 'SE',
            'engine' => '2.5L I4',
            'is_active' => true,
        ]);

        $supportedService = Service::query()->create([
            'category' => 'Brakes',
            'name' => 'Front Brake Pads',
            'slug' => 'front-brake-pads',
            'difficulty_level' => ServiceDifficultyLevel::Moderate,
            'default_labor_hours' => 1.5,
            'is_active' => true,
        ]);

        $unsupportedService = Service::query()->create([
            'category' => 'Engine & Powertrain',
            'name' => 'Custom Engine Rebuild',
            'slug' => 'custom-engine-rebuild',
            'difficulty_level' => ServiceDifficultyLevel::Master,
            'default_labor_hours' => null,
            'is_active' => true,
        ]);

        $shop = Shop::query()->create([
            'name' => 'SF Certified Auto',
            'slug' => 'sf-certified-auto',
            'email' => 'sf@auto.test',
            'phone' => '415-555-0190',
            'street' => '500 Van Ness Ave',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94102',
            'latitude' => 37.7793,
            'longitude' => -122.4193,
            'is_certified' => true,
            'is_active' => true,
            'base_labor_rate' => 160.00,
        ]);

        ShopService::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'service_id' => $supportedService->id,
            'pricing_type' => 'hourly',
            'hourly_rate' => 160.00,
            'is_active' => true,
        ]);

        /** @var EstimateCalculationService $calculator */
        $calculator = $this->app->make(EstimateCalculationService::class);

        $supported = $calculator->generateEstimate($vehicle, $supportedService, '94102');
        self::assertTrue($supported->is_supported_estimate);
        self::assertNotNull($supported->estimated_total_low);
        self::assertNotNull($supported->estimated_total_high);
        self::assertGreaterThanOrEqual((float) $supported->estimated_total_low, (float) $supported->estimated_total_high);

        $fallback = $calculator->generateEstimate($vehicle, $unsupportedService, '94102');
        self::assertFalse($fallback->is_supported_estimate);
        self::assertNull($fallback->estimated_total_low);
        self::assertNull($fallback->estimated_total_high);
    }

    public function test_idempotent_booking_and_bay_capacity_conflict_protection(): void
    {
        $vehicle = Vehicle::query()->create([
            'year' => 2023,
            'make' => 'Honda',
            'model' => 'Accord',
            'submodel' => 'EX',
            'engine' => '1.5L Turbo',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'category' => 'Maintenance & Fluids',
            'name' => 'Oil Change',
            'slug' => 'oil-change',
            'difficulty_level' => ServiceDifficultyLevel::Easy,
            'default_labor_hours' => 0.5,
            'is_active' => true,
        ]);

        $shop = Shop::query()->create([
            'name' => 'Bay Capacity Shop',
            'slug' => 'bay-capacity-shop',
            'email' => 'capacity@shop.test',
            'phone' => '415-555-0188',
            'street' => '600 Howard St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94105',
            'latitude' => 37.7879,
            'longitude' => -122.3965,
            'is_certified' => true,
            'is_active' => true,
            'base_labor_rate' => 150.00,
        ]);

        $targetDate = now()->addDays(2);

        ShopAvailabilitySlot::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'day_of_week' => $targetDate->dayOfWeek,
            'opens_at' => '08:00',
            'closes_at' => '17:00',
            'max_concurrent_bookings' => 1,
            'is_closed' => false,
        ]);

        /** @var BookingStateMachineService $stateMachine */
        $stateMachine = $this->app->make(BookingStateMachineService::class);

        $first = $stateMachine->createBookingRequest([
            'shop_id' => $shop->id,
            'vehicle_id' => $vehicle->id,
            'service_id' => $service->id,
            'idempotency_key' => 'idem-key-001',
            'preferred_date' => $targetDate->toDateString(),
            'preferred_time_slot' => 'morning',
            'customer_name' => 'Alice Driver',
            'customer_email' => 'alice@example.com',
            'customer_phone' => '415-555-0177',
        ]);

        $duplicate = $stateMachine->createBookingRequest([
            'shop_id' => $shop->id,
            'vehicle_id' => $vehicle->id,
            'service_id' => $service->id,
            'idempotency_key' => 'idem-key-001',
            'preferred_date' => $targetDate->toDateString(),
            'preferred_time_slot' => 'morning',
            'customer_name' => 'Alice Driver',
            'customer_email' => 'alice@example.com',
            'customer_phone' => '415-555-0177',
        ]);

        self::assertSame($first->id, $duplicate->id);

        $stateMachine->transition($first, BookingStatus::Confirmed);

        $second = $stateMachine->createBookingRequest([
            'shop_id' => $shop->id,
            'vehicle_id' => $vehicle->id,
            'service_id' => $service->id,
            'idempotency_key' => 'idem-key-002',
            'preferred_date' => $targetDate->toDateString(),
            'preferred_time_slot' => 'morning',
            'customer_name' => 'Bob Driver',
            'customer_email' => 'bob@example.com',
            'customer_phone' => '415-555-0178',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Bay capacity reached');

        $stateMachine->transition($second, BookingStatus::Confirmed);
    }
}
