<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ServiceDifficultyLevel;
use App\Enums\ShopMemberRole;
use App\Enums\UserRole;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopAvailabilitySlot;
use App\Models\ShopMember;
use App\Models\ShopService;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Platform Users (Admin, Shop Owner, Shop Staff, Customer)
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@repairpal.test'],
            [
                'name' => 'Platform Super Admin',
                'phone' => '+1-415-555-0100',
                'password' => Hash::make('Password123!'),
                'role' => UserRole::Admin,
                'is_active' => true,
                'mfa_enabled' => true,
                'email_verified_at' => now(),
            ],
        );

        $owner = User::query()->updateOrCreate(
            ['email' => 'owner@bayareamotors.test'],
            [
                'name' => 'Marcus Vance (Shop Owner)',
                'phone' => '+1-415-555-0142',
                'password' => Hash::make('Password123!'),
                'role' => UserRole::ShopOwner,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'customer@repairpal.test'],
            [
                'name' => 'Elena Rostova (Customer)',
                'phone' => '+1-415-555-0199',
                'password' => Hash::make('Password123!'),
                'role' => UserRole::Customer,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // 2. Pilot Certified Shop (San Francisco, CA 94102)
        $shop = Shop::query()->updateOrCreate(
            ['slug' => 'bay-area-precision-auto-sf'],
            [
                'name' => 'Bay Area Precision Auto Care',
                'email' => 'service@bayareamotors.test',
                'phone' => '(415) 555-0142',
                'street' => '450 Golden Gate Ave',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip_code' => '94102',
                'latitude' => 37.7816,
                'longitude' => -122.4156,
                'is_certified' => true,
                'is_active' => true,
                'base_labor_rate' => 165.00,
            ],
        );

        ShopMember::withoutGlobalScopes()->updateOrCreate(
            ['shop_id' => $shop->id, 'user_id' => $owner->id],
            [
                'role' => ShopMemberRole::Owner,
                'is_active' => true,
                'joined_at' => now(),
            ],
        );

        // 3. Master Repair Service Catalog
        $servicesData = [
            [
                'category' => 'Brakes',
                'name' => 'Front Brake Pad & Rotor Replacement',
                'slug' => 'front-brake-pad-rotor-replacement',
                'difficulty_level' => ServiceDifficultyLevel::Moderate,
                'default_labor_hours' => 1.8,
                'description' => 'Complete replacement of front axle brake pads and rotors plus caliper slide lubrication.',
            ],
            [
                'category' => 'Maintenance & Fluids',
                'name' => 'Full Synthetic Oil & Filter Change',
                'slug' => 'full-synthetic-oil-filter-change',
                'difficulty_level' => ServiceDifficultyLevel::Easy,
                'default_labor_hours' => 0.5,
                'description' => 'Up to 5 quarts of OEM-spec full synthetic motor oil, OEM filter, and multi-point inspection.',
            ],
            [
                'category' => 'Electrical & Battery',
                'name' => 'Alternator Replacement',
                'slug' => 'alternator-replacement',
                'difficulty_level' => ServiceDifficultyLevel::Moderate,
                'default_labor_hours' => 2.2,
                'description' => 'Charging system verification and replacement of alternator assembly and drive belt inspection.',
            ],
        ];

        foreach ($servicesData as $serviceItem) {
            $service = Service::query()->updateOrCreate(
                ['slug' => $serviceItem['slug']],
                [...$serviceItem, 'is_active' => true],
            );

            ShopService::withoutGlobalScopes()->updateOrCreate(
                ['shop_id' => $shop->id, 'service_id' => $service->id],
                [
                    'pricing_type' => $service->slug === 'full-synthetic-oil-filter-change' ? 'fixed' : 'hourly',
                    'hourly_rate' => $service->slug === 'full-synthetic-oil-filter-change' ? null : 165.00,
                    'fixed_price' => $service->slug === 'full-synthetic-oil-filter-change' ? 119.95 : null,
                    'estimated_minutes' => (int) round(((float) $service->default_labor_hours) * 60),
                    'is_active' => true,
                ],
            );
        }

        // 4. Shop Operating Hours (Mon-Sat)
        for ($day = 0; $day <= 6; $day++) {
            ShopAvailabilitySlot::withoutGlobalScopes()->updateOrCreate(
                ['shop_id' => $shop->id, 'day_of_week' => $day],
                [
                    'opens_at' => '08:00',
                    'closes_at' => '17:30',
                    'max_concurrent_bookings' => 3,
                    'is_closed' => $day === 0,
                ],
            );
        }

        // 5. Master Vehicle Taxonomy Seed
        $vehicles = [
            ['year' => 2022, 'make' => 'Toyota', 'model' => 'Camry', 'submodel' => 'SE', 'engine' => '2.5L I4 DOHC', 'drivetrain' => 'FWD', 'fuel_type' => 'Gasoline'],
            ['year' => 2022, 'make' => 'Toyota', 'model' => 'Camry', 'submodel' => 'XSE V6', 'engine' => '3.5L V6 DOHC', 'drivetrain' => 'FWD', 'fuel_type' => 'Gasoline'],
            ['year' => 2023, 'make' => 'Honda', 'model' => 'Accord', 'submodel' => 'EX', 'engine' => '1.5L I4 Turbo', 'drivetrain' => 'FWD', 'fuel_type' => 'Gasoline'],
            ['year' => 2021, 'make' => 'Ford', 'model' => 'F-150', 'submodel' => 'XLT', 'engine' => '3.5L V6 EcoBoost', 'drivetrain' => '4WD', 'fuel_type' => 'Gasoline'],
        ];

        foreach ($vehicles as $v) {
            Vehicle::query()->updateOrCreate(
                [
                    'year' => $v['year'],
                    'make' => $v['make'],
                    'model' => $v['model'],
                    'submodel' => $v['submodel'],
                    'engine' => $v['engine'],
                ],
                [
                    'drivetrain' => $v['drivetrain'],
                    'fuel_type' => $v['fuel_type'],
                    'is_active' => true,
                ],
            );
        }
    }
}
