<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class GeoSearchService
{
    private const METERS_PER_MILE = 1609.344;

    /**
     * Find active, certified repair shops within a given radius (in miles) of the target coordinates.
     *
     * Uses PostGIS `ST_DWithin` and `ST_Distance` against the `shops.coordinates` geography(Point, 4326)
     * GiST-indexed column, ordering results by ascending distance in miles.
     *
     * @return Collection<int, Shop>
     */
    public function findNearbyShops(
        float $lat,
        float $lng,
        int $radiusMiles = 25,
        ?string $serviceId = null,
        int $limit = 50,
    ): Collection {
        $this->assertValidCoordinates($lat, $lng);

        $clampedRadiusMiles = max(1, min($radiusMiles, 100));
        $radiusMeters = $clampedRadiusMiles * self::METERS_PER_MILE;

        $query = Shop::query()
            ->select('shops.*')
            ->where('shops.is_active', true)
            ->where('shops.is_certified', true)
            ->with(['services' => static function ($relation): void {
                $relation->wherePivot('is_active', true);
            }]);

        if ($serviceId !== null && $serviceId !== '') {
            $query->whereHas('shopServices', static function ($builder) use ($serviceId): void {
                $builder->where('service_id', $serviceId)
                    ->where('is_active', true);
            });
        }

        if ($this->supportsPostGisGeography()) {
            $pointGeographySql = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

            $query
                ->selectRaw(
                    "ROUND((ST_Distance(shops.coordinates, {$pointGeographySql}) / ?)::numeric, 2) AS distance_miles",
                    [$lng, $lat, self::METERS_PER_MILE],
                )
                ->whereRaw(
                    "ST_DWithin(shops.coordinates, {$pointGeographySql}, ?)",
                    [$lng, $lat, $radiusMeters],
                )
                ->orderBy('distance_miles', 'asc');
        } else {
            // Haversine spherical law of cosines fallback when PostGIS extension is unavailable in CI/local
            $haversineMilesSql = '(3958.7613 * acos(LEAST(1.0, GREATEST(-1.0, '
                . 'cos(radians(?)) * cos(radians(shops.latitude)) '
                . '* cos(radians(shops.longitude) - radians(?)) '
                . '+ sin(radians(?)) * sin(radians(shops.latitude))'
                . '))))';

            $query
                ->selectRaw("ROUND(({$haversineMilesSql})::numeric, 2) AS distance_miles", [$lat, $lng, $lat])
                ->whereRaw("{$haversineMilesSql} <= ?", [$lat, $lng, $lat, $clampedRadiusMiles])
                ->orderBy('distance_miles', 'asc');
        }

        return $query->limit(max(1, min($limit, 100)))->get();
    }

    /**
     * Resolve a 5-digit US ZIP code to centroid coordinates for radius search.
     *
     * @return array{zip_code: string, city: string, state: string, latitude: float, longitude: float}|null
     */
    public function resolveZipCoordinates(string $zipCode): ?array
    {
        $normalizedZip = substr(trim($zipCode), 0, 5);

        if (preg_match('/^[0-9]{5}$/', $normalizedZip) !== 1) {
            return null;
        }

        // First check if we have an active certified shop in that exact ZIP to anchor the centroid
        $shopInZip = Shop::query()
            ->where('is_active', true)
            ->where('zip_code', 'like', "{$normalizedZip}%")
            ->first(['city', 'state', 'latitude', 'longitude']);

        if ($shopInZip !== null) {
            return [
                'zip_code' => $normalizedZip,
                'city' => (string) $shopInZip->city,
                'state' => (string) $shopInZip->state,
                'latitude' => (float) $shopInZip->latitude,
                'longitude' => (float) $shopInZip->longitude,
            ];
        }

        // Fallback US Pilot Metro ZIP centroids
        $pilotMetroCentroids = [
            '94102' => ['city' => 'San Francisco', 'state' => 'CA', 'latitude' => 37.7793, 'longitude' => -122.4193],
            '94103' => ['city' => 'San Francisco', 'state' => 'CA', 'latitude' => 37.7726, 'longitude' => -122.4110],
            '94107' => ['city' => 'San Francisco', 'state' => 'CA', 'latitude' => 37.7621, 'longitude' => -122.3971],
            '90001' => ['city' => 'Los Angeles', 'state' => 'CA', 'latitude' => 33.9731, 'longitude' => -118.2479],
            '90210' => ['city' => 'Beverly Hills', 'state' => 'CA', 'latitude' => 34.0901, 'longitude' => -118.4065],
            '78701' => ['city' => 'Austin', 'state' => 'TX', 'latitude' => 30.2711, 'longitude' => -97.7437],
            '75201' => ['city' => 'Dallas', 'state' => 'TX', 'latitude' => 32.7876, 'longitude' => -96.7994],
            '60601' => ['city' => 'Chicago', 'state' => 'IL', 'latitude' => 41.8853, 'longitude' => -87.6219],
            '10001' => ['city' => 'New York', 'state' => 'NY', 'latitude' => 40.7506, 'longitude' => -73.9972],
            '30303' => ['city' => 'Atlanta', 'state' => 'GA', 'latitude' => 33.7537, 'longitude' => -84.3902],
            '33101' => ['city' => 'Miami', 'state' => 'FL', 'latitude' => 25.7751, 'longitude' => -80.1947],
            '98101' => ['city' => 'Seattle', 'state' => 'WA', 'latitude' => 47.6101, 'longitude' => -122.3344],
        ];

        if (isset($pilotMetroCentroids[$normalizedZip])) {
            return [
                'zip_code' => $normalizedZip,
                ...$pilotMetroCentroids[$normalizedZip],
            ];
        }

        return null;
    }

    private function assertValidCoordinates(float $lat, float $lng): void
    {
        if ($lat < -90.0 || $lat > 90.0) {
            throw new InvalidArgumentException("Latitude [{$lat}] must be between -90 and 90 degrees.");
        }

        if ($lng < -180.0 || $lng > 180.0) {
            throw new InvalidArgumentException("Longitude [{$lng}] must be between -180 and 180 degrees.");
        }
    }

    private function supportsPostGisGeography(): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        return (bool) DB::scalar(
            "SELECT EXISTS (
                SELECT 1
                FROM information_schema.columns
                WHERE table_name = 'shops' AND column_name = 'coordinates'
            )"
        );
    }
}
