<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\ShopQuote;
use App\Models\User;
use App\Services\BookingStateMachineService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BookingController
{
    public function __construct(
        private readonly BookingStateMachineService $bookingStateMachine,
    ) {}

    /**
     * POST /api/v1/bookings
     * Create an idempotent appointment request (`AUTO-06-T01..T03 & T07`).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_id' => ['required', 'uuid', 'exists:shops,id'],
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'shop_quote_id' => ['nullable', 'uuid'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'preferred_time_slot' => ['required', 'string', 'in:morning,afternoon,dropoff'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'issue_description' => ['nullable', 'string', 'max:2000'],
        ]);

        // Verify cross-tenant quote integrity before attempting insert
        if (! empty($validated['shop_quote_id'])) {
            $quoteBelongsToShop = ShopQuote::withoutGlobalScopes()
                ->whereKey($validated['shop_quote_id'])
                ->where('shop_id', $validated['shop_id'])
                ->exists();

            if (! $quoteBelongsToShop) {
                return response()->json([
                    'error' => 'The specified shop quote does not belong to the selected repair shop.',
                ], 422);
            }
        }

        $headerKey = $request->header('Idempotency-Key');
        $rawClientKey = is_string($headerKey) && trim($headerKey) !== ''
            ? trim($headerKey)
            : (isset($validated['idempotency_key']) && trim((string) $validated['idempotency_key']) !== ''
                ? trim((string) $validated['idempotency_key'])
                : '');

        // Bind the idempotency key to (shop_id, customer_email, vehicle_id, service_id, date, slot, client_key)
        // via SHA-256 so two different customers or services can never collide on truncated client keys (`[HIGH-01]`)
        $scopedIdempotencyKey = hash('sha256', implode('|', [
            (string) $validated['shop_id'],
            strtolower(trim((string) $validated['customer_email'])),
            (string) $validated['vehicle_id'],
            (string) $validated['service_id'],
            (string) $validated['preferred_date'],
            (string) $validated['preferred_time_slot'],
            $rawClientKey,
        ]));

        $booking = $this->bookingStateMachine->createBookingRequest([
            'shop_id' => (string) $validated['shop_id'],
            'customer_id' => $request->user()?->id,
            'vehicle_id' => (string) $validated['vehicle_id'],
            'service_id' => (string) $validated['service_id'],
            'shop_quote_id' => isset($validated['shop_quote_id']) ? (string) $validated['shop_quote_id'] : null,
            'idempotency_key' => $scopedIdempotencyKey,
            'preferred_date' => (string) $validated['preferred_date'],
            'preferred_time_slot' => (string) $validated['preferred_time_slot'],
            'customer_name' => (string) $validated['customer_name'],
            'customer_email' => strtolower(trim((string) $validated['customer_email'])),
            'customer_phone' => (string) $validated['customer_phone'],
            'issue_description' => isset($validated['issue_description']) ? (string) $validated['issue_description'] : null,
        ], $request->user()?->id);

        return response()->json([
            'data' => $booking->load(['shop', 'vehicle', 'service']),
        ], 201);
    }

    /**
     * GET /api/v1/bookings/{id}
     * Retrieve authorized booking details (`AUTO-06-T07`).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Booking $booking */
        $booking = Booking::withoutGlobalScopes()
            ->with(['shop', 'vehicle', 'service', 'statusEvents'])
            ->findOrFail($id);

        if (! $this->isAuthorizedForBooking($request, $booking)) {
            return response()->json(['error' => 'Unauthorized to view this booking.'], 403);
        }

        return response()->json([
            'data' => $booking,
        ]);
    }

    /**
     * POST /api/v1/bookings/{id}/cancel
     * Cancel an appointment with state-machine rules (`AUTO-06-T02 & T07`).
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'customer_email' => ['nullable', 'email'],
        ]);

        /** @var Booking $booking */
        $booking = Booking::withoutGlobalScopes()->findOrFail($id);

        if (! $this->isAuthorizedForBooking($request, $booking)) {
            return response()->json(['error' => 'Unauthorized to cancel this booking.'], 403);
        }

        if (! $booking->status->isCustomerCancellable()) {
            return response()->json([
                'error' => "Appointment in status [{$booking->status->value}] can no longer be cancelled online.",
            ], 422);
        }

        try {
            $updated = $this->bookingStateMachine->transition(
                booking: $booking,
                targetStatus: BookingStatus::Cancelled,
                actorUserId: $request->user()?->id,
                note: (string) $validated['reason'],
            );
        } catch (DomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $updated,
        ]);
    }

    /**
     * Strict booking authorization (`[CRITICAL-02]`):
     * - Registered customer bookings (`customer_id !== null`) require an authenticated session
     *   matching `customer_id`, an active member of the booking's `Shop`, or a platform `Admin`.
     * - Guest bookings (`customer_id === null`) require the caller to possess the exact `Booking`
     *   UUID (`{id}`) AND matching `customer_email`.
     */
    private function isAuthorizedForBooking(Request $request, Booking $booking): bool
    {
        $user = $request->user();

        if ($user instanceof User && $user->is_active) {
            if ($user->role === UserRole::Admin) {
                return true;
            }

            if ($booking->customer_id !== null && $booking->customer_id === $user->id) {
                return true;
            }

            if ($booking->shop !== null && $user->canAccessTenant($booking->shop)) {
                return true;
            }
        }

        if ($booking->customer_id !== null) {
            return false;
        }

        $providedEmail = strtolower(trim((string) ($request->input('customer_email') ?? $request->query('customer_email', ''))));

        return $providedEmail !== '' && $providedEmail === strtolower(trim($booking->customer_email));
    }
}
