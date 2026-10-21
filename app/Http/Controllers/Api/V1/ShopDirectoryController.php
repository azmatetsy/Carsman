<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Shop;
use App\Services\GeoSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShopDirectoryController
{
    public function __construct(
        private readonly GeoSearchService $geoSearchService,
    ) {}

    /**
     * GET /api/v1/shops
     * Browse active certified shops by ZIP code or coordinates + radius.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zip_code' => ['nullable', 'string', 'regex:/^[0-9]{5}(-[0-9]{4})?$/'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_miles' => ['nullable', 'integer', 'min:1', 'max:100'],
            'service_id' => ['nullable', 'uuid'],
        ]);

        $resolvedLocation = null;
        if (! empty($validated['zip_code'])) {
            $resolvedLocation = $this->geoSearchService->resolveZipCoordinates((string) $validated['zip_code']);
        }

        $lat = isset($validated['lat'])
            ? (float) $validated['lat']
            : ($resolvedLocation['latitude'] ?? null);

        $lng = isset($validated['lng'])
            ? (float) $validated['lng']
            : ($resolvedLocation['longitude'] ?? null);

        if ($lat === null || $lng === null) {
            return response()->json([
                'data' => [],
                'error' => 'Unable to resolve coordinates for the provided ZIP code. Please provide a valid pilot ZIP code or coordinates.',
            ], 422);
        }

        $radiusMiles = isset($validated['radius_miles']) ? (int) $validated['radius_miles'] : 25;
        $serviceId = isset($validated['service_id']) ? (string) $validated['service_id'] : null;

        $shops = $this->geoSearchService->findNearbyShops(
            lat: $lat,
            lng: $lng,
            radiusMiles: $radiusMiles,
            serviceId: $serviceId,
        );

        return response()->json([
            'data' => $shops,
            'meta' => [
                'count' => $shops->count(),
                'radius_miles' => $radiusMiles,
                'location' => $resolvedLocation ?? [
                    'city' => 'Custom Coordinates',
                    'state' => 'US',
                    'latitude' => $lat,
                    'longitude' => $lng,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/shops/{id}
     * Public shop detail endpoint (restricted to active, certified shops).
     */
    public function show(string $id): JsonResponse
    {
        $shop = Shop::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->where('is_certified', true)
            ->with([
                'services' => static fn ($q) => $q->wherePivot('is_active', true),
                'availabilitySlots' => static fn ($q) => $q->withoutGlobalScopes()->orderBy('day_of_week'),
            ])
            ->firstOrFail();

        return response()->json([
            'data' => $shop,
        ]);
    }
}
