<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Vehicle;
use App\Services\NhtsaVinDecoderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VehicleCatalogController
{
    public function __construct(
        private readonly NhtsaVinDecoderService $vinDecoderService,
    ) {}

    /**
     * GET /api/v1/vehicles/makes
     * Returns active vehicle taxonomy makes (and optional cascading year/make/model filter).
     */
    public function makes(Request $request): JsonResponse
    {
        $year = $request->query('year');
        $make = $request->query('make');

        $query = Vehicle::query()->where('is_active', true);

        if (is_numeric($year)) {
            $query->where('year', (int) $year);
        }

        if (is_string($make) && $make !== '') {
            $query->where('make', $make);
        }

        $makes = (clone $query)
            ->select('make')
            ->distinct()
            ->orderBy('make')
            ->pluck('make');

        $vehicles = $query
            ->orderByDesc('year')
            ->orderBy('make')
            ->orderBy('model')
            ->limit(250)
            ->get(['id', 'year', 'make', 'model', 'submodel', 'engine']);

        return response()->json([
            'data' => [
                'makes' => $makes,
                'vehicles' => $vehicles,
            ],
        ]);
    }

    /**
     * POST /api/v1/vehicles/decode-vin
     * Decode a 17-character VIN via NHTSA vPIC and map to our vehicle catalog.
     */
    public function decodeVin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vin' => ['required', 'string', 'size:17'],
        ]);

        $result = $this->vinDecoderService->decodeVin((string) $validated['vin']);

        return response()->json(
            $result,
            $result['success'] ? 200 : 422,
        );
    }
}
