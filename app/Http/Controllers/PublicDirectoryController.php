<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Shop;
use App\Models\Vehicle;
use App\Services\GeoSearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicDirectoryController
{
    public function __construct(
        private readonly GeoSearchService $geoSearchService,
    ) {}

    /**
     * Render the public home & directory search page (`/`).
     */
    public function index(Request $request): Response
    {
        $defaultZip = (string) $request->query('zip_code', '94102');
        $resolved = $this->geoSearchService->resolveZipCoordinates($defaultZip) ?? [
            'zip_code' => '94102',
            'city' => 'San Francisco',
            'state' => 'CA',
            'latitude' => 37.7793,
            'longitude' => -122.4193,
        ];

        $initialShops = $this->geoSearchService->findNearbyShops(
            lat: $resolved['latitude'],
            lng: $resolved['longitude'],
            radiusMiles: 25,
        );

        $vehicleCatalog = Vehicle::query()
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderBy('make')
            ->orderBy('model')
            ->limit(500)
            ->get(['id', 'year', 'make', 'model', 'submodel', 'engine']);

        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'slug']);

        return Inertia::render('Home', [
            'vehicleCatalog' => $vehicleCatalog,
            'services' => $services,
            'initialShops' => $initialShops,
            'initialZip' => $resolved['zip_code'],
        ]);
    }

    /**
     * Render public shop detail page (`/shops/{shop:slug}`).
     * Unapproved or inactive shops return 404.
     */
    public function show(Shop $shop): Response
    {
        abort_unless($shop->is_active && $shop->is_certified, 404);

        $shop->load([
            'services' => static fn ($query) => $query->wherePivot('is_active', true),
            'availabilitySlots' => static fn ($query) => $query->withoutGlobalScopes()->orderBy('day_of_week'),
        ]);

        $vehicleCatalog = Vehicle::query()
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderBy('make')
            ->orderBy('model')
            ->limit(500)
            ->get(['id', 'year', 'make', 'model', 'submodel', 'engine']);

        return Inertia::render('ShopDetail', [
            'shop' => $shop,
            'vehicleCatalog' => $vehicleCatalog,
        ]);
    }
}
