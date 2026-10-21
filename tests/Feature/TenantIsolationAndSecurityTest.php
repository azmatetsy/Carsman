<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ShopMemberRole;
use App\Enums\UserRole;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\ShopService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;

final class TenantIsolationAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_Cross_tenant_shop_id_mutation_throws_runtime_exception(): void
    {
        $shopA = Shop::query()->create([
            'name' => 'Shop Alpha',
            'slug' => 'shop-alpha',
            'email' => 'alpha@shop.test',
            'phone' => '415-555-0101',
            'street' => '100 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94102',
            'latitude' => 37.7793,
            'longitude' => -122.4193,
            'is_certified' => true,
            'is_active' => true,
            'base_labor_rate' => 150.00,
        ]);

        $shopB = Shop::query()->create([
            'name' => 'Shop Beta',
            'slug' => 'shop-beta',
            'email' => 'beta@shop.test',
            'phone' => '415-555-0102',
            'street' => '200 Market St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94103',
            'latitude' => 37.7726,
            'longitude' => -122.4110,
            'is_certified' => true,
            'is_active' => true,
            'base_labor_rate' => 160.00,
        ]);

        $service = Service::query()->create([
            'category' => 'Brakes',
            'name' => 'Brake Pad Replacement',
            'slug' => 'brake-pad-replacement',
            'default_labor_hours' => 1.5,
            'is_active' => true,
        ]);

        $shopService = ShopService::withoutGlobalScopes()->create([
            'shop_id' => $shopA->id,
            'service_id' => $service->id,
            'pricing_type' => 'hourly',
            'hourly_rate' => 150.00,
            'is_active' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant isolation violation: reassigning shop_id across tenants is prohibited.');

        $shopService->update(['shop_id' => $shopB->id]);
    }

    public function test_user_cannot_access_foreign_shop_tenant(): void
    {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a@shop.test',
            'password' => 'secret1234',
            'role' => UserRole::ShopOwner,
            'is_active' => true,
        ]);

        $shopA = Shop::query()->create([
            'name' => 'Shop A',
            'slug' => 'shop-a',
            'email' => 'a@shop.test',
            'phone' => '415-555-0111',
            'street' => '10 First St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94102',
            'latitude' => 37.7793,
            'longitude' => -122.4193,
            'is_certified' => true,
            'is_active' => true,
            'base_labor_rate' => 155.00,
        ]);

        $shopB = Shop::query()->create([
            'name' => 'Shop B',
            'slug' => 'shop-b',
            'email' => 'b@shop.test',
            'phone' => '415-555-0222',
            'street' => '20 Second St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94103',
            'latitude' => 37.7726,
            'longitude' => -122.4110,
            'is_certified' => true,
            'is_active' => true,
            'base_labor_rate' => 165.00,
        ]);

        ShopMember::withoutGlobalScopes()->create([
            'shop_id' => $shopA->id,
            'user_id' => $ownerA->id,
            'role' => ShopMemberRole::Owner,
            'is_active' => true,
        ]);

        self::assertTrue($ownerA->canAccessTenant($shopA));
        self::assertFalse($ownerA->canAccessTenant($shopB));
    }
}
