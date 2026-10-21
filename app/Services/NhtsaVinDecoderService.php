<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NhtsaVinDecoderService
{
    private const BASE_URL = 'https://vpic.nhtsa.dot.gov/api/vehicles';

    private const CACHE_TTL_SECONDS = 86400 * 7; // 7 days

    /**
     * Validate a 17-character ISO 3779 VIN (excluding I, O, Q).
     */
    public function isValidVinFormat(string $vin): bool
    {
        $normalized = strtoupper(trim($vin));

        return preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $normalized) === 1;
    }

    /**
     * Decode a 17-character VIN via the official NHTSA vPIC API, map it to our `vehicles`
     * taxonomy (Year, Make, Model, Submodel, Engine), and persist/sync the specification cache.
     *
     * @return array{
     *     success: bool,
     *     vin: string,
     *     vehicle: Vehicle|null,
     *     attributes: array{
     *         year: int,
     *         make: string,
     *         model: string,
     *         submodel: string,
     *         engine: string,
     *         transmission: string|null,
     *         drivetrain: string|null,
     *         fuel_type: string|null,
     *         nhtsa_make_id: int|null,
     *         nhtsa_model_id: int|null
     *     }|null,
     *     error: string|null
     * }
     */
    public function decodeVin(string $vin, bool $persistToCatalog = true): array
    {
        $normalizedVin = strtoupper(trim($vin));

        if (! $this->isValidVinFormat($normalizedVin)) {
            return [
                'success' => false,
                'vin' => $normalizedVin,
                'vehicle' => null,
                'attributes' => null,
                'error' => 'Invalid VIN format. A valid VIN must be 17 alphanumeric characters (excluding I, O, and Q).',
            ];
        }

        $cacheKey = "nhtsa_vin_decode:v1:{$normalizedVin}";

        try {
            /** @var array<string, mixed>|null $rawResult */
            $rawResult = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($normalizedVin): ?array {
                $response = Http::baseUrl(self::BASE_URL)
                    ->acceptJson()
                    ->timeout(6)
                    ->connectTimeout(3)
                    ->retry(2, 200, fn (Throwable $exception): bool => $exception instanceof ConnectionException)
                    ->get("/DecodeVinValuesExtended/{$normalizedVin}", [
                        'format' => 'json',
                    ])
                    ->throw();

                /** @var array<string, mixed>|null $firstRow */
                $firstRow = $response->json('Results.0');

                return is_array($firstRow) ? $firstRow : null;
            });
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('NHTSA vPIC API request failed during VIN decode', [
                'vin' => $normalizedVin,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'vin' => $normalizedVin,
                'vehicle' => null,
                'attributes' => null,
                'error' => 'Vehicle identification service is temporarily unavailable. Please select your Year, Make, and Model manually.',
            ];
        } catch (Throwable $exception) {
            Log::error('Unexpected error during NHTSA VIN decode', [
                'vin' => $normalizedVin,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'vin' => $normalizedVin,
                'vehicle' => null,
                'attributes' => null,
                'error' => 'Unable to decode VIN at this time. Please select your vehicle manually.',
            ];
        }

        if ($rawResult === null) {
            Cache::forget($cacheKey);

            return [
                'success' => false,
                'vin' => $normalizedVin,
                'vehicle' => null,
                'attributes' => null,
                'error' => 'No vehicle specification returned by NHTSA for this VIN.',
            ];
        }

        $errorCode = trim((string) ($rawResult['ErrorCode'] ?? ''));
        $errorCodes = array_filter(array_map('trim', explode(',', $errorCode)), static fn (string $c): bool => $c !== '');

        $year = (int) ($rawResult['ModelYear'] ?? 0);
        $make = $this->normalizeTitle((string) ($rawResult['Make'] ?? ''));
        $model = trim((string) ($rawResult['Model'] ?? ''));

        // NHTSA ErrorCode "0" means clean decode; if essential YMM fields are missing or ErrorCode is non-zero without YMM
        if (($errorCodes !== [] && ! in_array('0', $errorCodes, true)) || $year < 1980 || $make === '' || $model === '') {
            Cache::forget($cacheKey);

            $errorText = trim((string) ($rawResult['ErrorText'] ?? 'Unrecognized or incomplete VIN data from NHTSA.'));

            return [
                'success' => false,
                'vin' => $normalizedVin,
                'vehicle' => null,
                'attributes' => null,
                'error' => "Unable to verify VIN: {$errorText}",
            ];
        }

        $submodel = trim((string) ($rawResult['Trim'] ?? $rawResult['Series'] ?? ''));
        $engine = $this->buildEngineSpecification($rawResult);
        $transmission = $this->nullableString($rawResult['TransmissionStyle'] ?? null);
        $drivetrain = $this->normalizeDrivetrain($this->nullableString($rawResult['DriveType'] ?? null));
        $fuelType = $this->nullableString($rawResult['FuelTypePrimary'] ?? null);
        $nhtsaMakeId = isset($rawResult['MakeID']) && is_numeric($rawResult['MakeID']) ? (int) $rawResult['MakeID'] : null;
        $nhtsaModelId = isset($rawResult['ModelID']) && is_numeric($rawResult['ModelID']) ? (int) $rawResult['ModelID'] : null;

        $attributes = [
            'year' => $year,
            'make' => $make,
            'model' => $model,
            'submodel' => $submodel,
            'engine' => $engine,
            'transmission' => $transmission,
            'drivetrain' => $drivetrain,
            'fuel_type' => $fuelType,
            'nhtsa_make_id' => $nhtsaMakeId,
            'nhtsa_model_id' => $nhtsaModelId,
        ];

        $vehicle = null;
        if ($persistToCatalog) {
            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->updateOrCreate(
                [
                    'year' => $attributes['year'],
                    'make' => $attributes['make'],
                    'model' => $attributes['model'],
                    'submodel' => $attributes['submodel'],
                    'engine' => $attributes['engine'],
                ],
                [
                    'transmission' => $attributes['transmission'],
                    'drivetrain' => $attributes['drivetrain'],
                    'fuel_type' => $attributes['fuel_type'],
                    'nhtsa_make_id' => $attributes['nhtsa_make_id'],
                    'nhtsa_model_id' => $attributes['nhtsa_model_id'],
                    'spec_payload' => [
                        'body_class' => $rawResult['BodyClass'] ?? null,
                        'engine_cylinders' => $rawResult['EngineCylinders'] ?? null,
                        'displacement_l' => $rawResult['DisplacementL'] ?? null,
                        'engine_model' => $rawResult['EngineModel'] ?? null,
                        'plant_country' => $rawResult['PlantCountry'] ?? null,
                        'vehicle_type' => $rawResult['VehicleType'] ?? null,
                    ],
                    'is_active' => true,
                ],
            );
        }

        return [
            'success' => true,
            'vin' => $normalizedVin,
            'vehicle' => $vehicle,
            'attributes' => $attributes,
            'error' => null,
        ];
    }

    /**
     * Compose a normalized engine descriptor from NHTSA vPIC fields (e.g., "3.5L V6 DOHC").
     *
     * @param  array<string, mixed>  $raw
     */
    private function buildEngineSpecification(array $raw): string
    {
        $parts = [];

        $displacementL = $this->nullableString($raw['DisplacementL'] ?? null);
        if ($displacementL !== null && is_numeric($displacementL)) {
            $parts[] = number_format((float) $displacementL, 1) . 'L';
        }

        $configuration = $this->nullableString($raw['EngineConfiguration'] ?? null);
        $cylinders = $this->nullableString($raw['EngineCylinders'] ?? null);

        if ($cylinders !== null) {
            $prefix = match ($configuration) {
                'V-Shaped' => 'V',
                'In-Line' => 'I',
                'Horizontally Opposed (Boxer)' => 'H',
                default => '',
            };
            $parts[] = $prefix !== '' ? "{$prefix}{$cylinders}" : "{$cylinders}-Cyl";
        }

        $valveTrain = $this->nullableString($raw['ValveTrainDesign'] ?? null);
        if ($valveTrain !== null) {
            $parts[] = $valveTrain;
        }

        $fuelType = $this->nullableString($raw['FuelTypePrimary'] ?? null);
        if ($fuelType !== null && stripos($fuelType, 'Electric') !== false) {
            $parts[] = 'Electric';
        }

        $composed = trim(implode(' ', array_unique($parts)));

        if ($composed !== '') {
            return substr($composed, 0, 150);
        }

        return substr((string) ($raw['EngineModel'] ?? 'Standard'), 0, 150);
    }

    private function normalizeDrivetrain(?string $rawDriveType): ?string
    {
        if ($rawDriveType === null) {
            return null;
        }

        $upper = strtoupper($rawDriveType);

        return match (true) {
            str_contains($upper, 'FWD') || str_contains($upper, 'FRONT') => 'FWD',
            str_contains($upper, 'RWD') || str_contains($upper, 'REAR') => 'RWD',
            str_contains($upper, 'AWD') || str_contains($upper, 'ALL') => 'AWD',
            str_contains($upper, '4WD') || str_contains($upper, '4X4') => '4WD',
            default => substr($rawDriveType, 0, 60),
        };
    }

    private function normalizeTitle(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        // Preserve 3-letter uppercase acronyms like BMW, GMC, RAM, KIA
        if (strlen($trimmed) <= 3) {
            return strtoupper($trimmed);
        }

        return ucwords(strtolower($trimmed));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '' || strtolower($trimmed) === 'not applicable') {
            return null;
        }

        return $trimmed;
    }
}
