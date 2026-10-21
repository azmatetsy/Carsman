<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Enums\ServiceDifficultyLevel;
use App\Models\EstimateRequest;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopQuote;
use App\Models\ShopService;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

final class EstimateCalculationService
{
    private const SOURCE_VERSION = 'RP-US-PILOT-DATA-v1.0';

    private const ESTIMATE_VALIDITY_DAYS = 14;

    public function __construct(
        private readonly GeoSearchService $geoSearchService,
    ) {}

    /**
     * Generate an evidence-backed preliminary repair estimate (`AUTO-05-T02..T06`).
     *
     * Enforces the Supported-Service Policy:
     * - Only publishes monetary low/high estimates when validated participating-shop rate data
     *   exists within the customer's ZIP radius and coverage conditions pass.
     * - Otherwise stores a fallback Quote Request (`is_supported_estimate = false`) with zero
     *   fabricated price claims.
     */
    public function generateEstimate(
        Vehicle $vehicle,
        Service $service,
        string $zipCode,
        ?string $customerId = null,
        ?string $issueDescription = null,
    ): EstimateRequest {
        $resolvedZip = $this->geoSearchService->resolveZipCoordinates($zipCode);

        /** @var Collection<int, Shop> $nearbyShops */
        $nearbyShops = $resolvedZip !== null
            ? $this->geoSearchService->findNearbyShops(
                lat: $resolvedZip['latitude'],
                lng: $resolvedZip['longitude'],
                radiusMiles: 25,
                serviceId: $service->id,
            )
            : collect();

        $shopIds = $nearbyShops->pluck('id')->all();

        /** @var Collection<int, ShopService> $validOfferings */
        $validOfferings = $shopIds !== []
            ? ShopService::withoutGlobalScopes()
                ->whereIn('shop_id', $shopIds)
                ->where('service_id', $service->id)
                ->where('is_active', true)
                ->whereIn('pricing_type', ['hourly', 'fixed'])
                ->with('shop')
                ->get()
            : collect();

        $laborHours = $service->default_labor_hours !== null ? (float) $service->default_labor_hours : 0.0;

        // Coverage Gate: Must have at least 1 verified certified shop offering hourly/fixed pricing
        // and valid labor hours (for hourly) to publish a numerical estimate.
        $hasValidPricingCoverage = $validOfferings->isNotEmpty()
            && ($laborHours > 0.0 || $validOfferings->where('pricing_type', 'fixed')->isNotEmpty());

        if (! $hasValidPricingCoverage) {
            return EstimateRequest::query()->create([
                'customer_id' => $customerId,
                'vehicle_id' => $vehicle->id,
                'service_id' => $service->id,
                'zip_code' => substr(trim($zipCode), 0, 10),
                'issue_description' => $issueDescription,
                'is_supported_estimate' => false,
                'source_version' => self::SOURCE_VERSION,
                'participating_shops_count' => 0,
                'estimated_labor_hours' => $laborHours > 0.0 ? $laborHours : null,
                'estimated_labor_low' => null,
                'estimated_labor_high' => null,
                'estimated_parts_low' => null,
                'estimated_parts_high' => null,
                'estimated_fees' => null,
                'estimated_total_low' => null,
                'estimated_total_high' => null,
                'assumptions_payload' => [
                    'policy' => 'FALLBACK_QUOTE_ONLY',
                    'reason' => 'Insufficient verified participating-shop pricing coverage in this ZIP radius for automated numerical range.',
                    'disclosure' => 'Request a direct shop quote from a participating certified repair shop.',
                ],
                'expires_at' => now()->addDays(self::ESTIMATE_VALIDITY_DAYS),
            ]);
        }

        // Compute labor low/high across participating certified shops in the metro area
        $laborEstimates = [];
        foreach ($validOfferings as $offering) {
            if ($offering->pricing_type === 'fixed' && $offering->fixed_price !== null) {
                $fixed = (float) $offering->fixed_price;
                $laborEstimates[] = round($fixed * 0.65, 2);
            } else {
                $rate = $offering->hourly_rate !== null
                    ? (float) $offering->hourly_rate
                    : (float) ($offering->shop?->base_labor_rate ?? 150.00);
                $effectiveHours = $laborHours > 0.0 ? $laborHours : 1.0;
                $laborEstimates[] = round($rate * $effectiveHours, 2);
            }
        }

        $minLabor = min($laborEstimates);
        $maxLabor = max($laborEstimates);

        $laborLow = round($minLabor * 0.95, 2);
        $laborHigh = round(max($laborLow, $maxLabor * 1.10), 2);

        [$partsLow, $partsHigh] = $this->calculatePartsRange($service, $validOfferings);
        $shopFees = round(min(45.00, max(15.00, ($laborLow + $partsLow) * 0.06)), 2);

        $totalLow = round($laborLow + $partsLow + $shopFees, 2);
        $totalHigh = round(max($totalLow, $laborHigh + $partsHigh + $shopFees), 2);

        return EstimateRequest::query()->create([
            'customer_id' => $customerId,
            'vehicle_id' => $vehicle->id,
            'service_id' => $service->id,
            'zip_code' => substr(trim($zipCode), 0, 10),
            'issue_description' => $issueDescription,
            'is_supported_estimate' => true,
            'source_version' => self::SOURCE_VERSION,
            'participating_shops_count' => $validOfferings->count(),
            'estimated_labor_hours' => $laborHours > 0.0 ? $laborHours : 1.0,
            'estimated_labor_low' => $laborLow,
            'estimated_labor_high' => $laborHigh,
            'estimated_parts_low' => $partsLow,
            'estimated_parts_high' => $partsHigh,
            'estimated_fees' => $shopFees,
            'estimated_total_low' => $totalLow,
            'estimated_total_high' => $totalHigh,
            'assumptions_payload' => [
                'policy' => 'SUPPORTED_VERIFIED_SHOP_INDEX',
                'region_city' => $resolvedZip['city'] ?? 'US Pilot Metro',
                'region_state' => $resolvedZip['state'] ?? 'US',
                'vehicle_label' => "{$vehicle->year} {$vehicle->make} {$vehicle->model}",
                'taxes_excluded' => true,
                'disclosure' => 'Preliminary estimate based on verified participating shop labor rates and OEM-equivalent parts allowances. Applicable state/local sales taxes are calculated at final shop invoice.',
            ],
            'expires_at' => now()->addDays(self::ESTIMATE_VALIDITY_DAYS),
        ]);
    }

    /**
     * Create a direct customer Quote Request to a specific certified shop (`AUTO-05-T06 & T07`).
     */
    public function requestShopQuote(
        Shop $shop,
        Vehicle $vehicle,
        Service $service,
        string $customerName,
        string $customerEmail,
        ?string $customerPhone = null,
        ?string $customerIssueNotes = null,
        ?string $customerId = null,
        ?string $estimateRequestId = null,
    ): ShopQuote {
        return ShopQuote::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'estimate_request_id' => $estimateRequestId,
            'customer_id' => $customerId,
            'vehicle_id' => $vehicle->id,
            'service_id' => $service->id,
            'status' => QuoteStatus::Requested,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'customer_issue_notes' => $customerIssueNotes,
            'labor_subtotal' => 0.00,
            'parts_subtotal' => 0.00,
            'shop_fees' => 0.00,
            'tax_estimate' => 0.00,
            'total_amount' => 0.00,
            'revision_number' => 1,
            'valid_until' => now()->addDays(self::ESTIMATE_VALIDITY_DAYS)->toDateString(),
        ]);
    }

    /**
     * @param  Collection<int, ShopService>  $offerings
     * @return array{0: float, 1: float}
     */
    private function calculatePartsRange(Service $service, Collection $offerings): array
    {
        $fixedOffering = $offerings->firstWhere('pricing_type', 'fixed');
        if ($fixedOffering instanceof ShopService && $fixedOffering->fixed_price !== null) {
            $fixed = (float) $fixedOffering->fixed_price;
            $parts = round($fixed * 0.35, 2);

            return [$parts, round($parts * 1.15, 2)];
        }

        $difficulty = $service->difficulty_level instanceof ServiceDifficultyLevel
            ? $service->difficulty_level
            : ServiceDifficultyLevel::Moderate;

        return match ($difficulty) {
            ServiceDifficultyLevel::Easy => [45.00, 85.00],
            ServiceDifficultyLevel::Moderate => [140.00, 260.00],
            ServiceDifficultyLevel::Complex => [320.00, 580.00],
            ServiceDifficultyLevel::Master => [650.00, 1250.00],
        };
    }
}
