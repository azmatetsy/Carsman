<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Service;
use App\Models\Shop;
use App\Models\Vehicle;
use App\Services\EstimateCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EstimateController
{
    public function __construct(
        private readonly EstimateCalculationService $estimateService,
    ) {}

    /**
     * POST /api/v1/estimates
     * Generate a provenance-backed repair estimate or fallback quote-only response (`AUTO-05-T03..T06`).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'zip_code' => ['required', 'string', 'regex:/^[0-9]{5}(-[0-9]{4})?$/'],
            'issue_description' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var Vehicle $vehicle */
        $vehicle = Vehicle::query()->findOrFail($validated['vehicle_id']);

        /** @var Service $service */
        $service = Service::query()->where('is_active', true)->findOrFail($validated['service_id']);

        $estimate = $this->estimateService->generateEstimate(
            vehicle: $vehicle,
            service: $service,
            zipCode: (string) $validated['zip_code'],
            customerId: $request->user()?->id,
            issueDescription: isset($validated['issue_description']) ? (string) $validated['issue_description'] : null,
        );

        return response()->json([
            'data' => $estimate->load(['vehicle', 'service']),
        ], 201);
    }

    /**
     * POST /api/v1/quotes
     * Submit a direct customer quote request to a certified shop (`AUTO-05-T06 & T07`).
     */
    public function requestShopQuote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_id' => ['required', 'uuid', 'exists:shops,id'],
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'estimate_request_id' => ['nullable', 'uuid', 'exists:estimate_requests,id'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_issue_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var Shop $shop */
        $shop = Shop::query()
            ->where('is_active', true)
            ->where('is_certified', true)
            ->findOrFail($validated['shop_id']);

        /** @var Vehicle $vehicle */
        $vehicle = Vehicle::query()->findOrFail($validated['vehicle_id']);

        /** @var Service $service */
        $service = Service::query()->where('is_active', true)->findOrFail($validated['service_id']);

        $quote = $this->estimateService->requestShopQuote(
            shop: $shop,
            vehicle: $vehicle,
            service: $service,
            customerName: (string) $validated['customer_name'],
            customerEmail: (string) $validated['customer_email'],
            customerPhone: isset($validated['customer_phone']) ? (string) $validated['customer_phone'] : null,
            customerIssueNotes: isset($validated['customer_issue_notes']) ? (string) $validated['customer_issue_notes'] : null,
            customerId: $request->user()?->id,
            estimateRequestId: isset($validated['estimate_request_id']) ? (string) $validated['estimate_request_id'] : null,
        );

        return response()->json([
            'data' => $quote,
        ], 201);
    }
}
