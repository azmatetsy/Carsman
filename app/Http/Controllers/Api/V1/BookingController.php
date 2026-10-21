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
        $idempotencyKey = is_string($headerKey) && trim($headerKey) !== ''
            ? substr(trim($headerKey), 0, 64)
            : (isset($validated['idempotency_key']) && trim((string) $validated['idempotency_key']) !== ''
                ? trim((string) $validated['idempotency_key'])
                : substr(hash('sha256', implode('|', [
                    $validated['shop_id'],
                    $validated['vehicle_id'],
                    $validated['service_id'],
                    $validated['preferred_date'],
                    $validated['preferred_time_slot'],
                    strtolower((string) $validated['customer_email']),
                ])), 0, 64));

        $booking = $this->bookingStateMachine->createBookingRequest([
            'shop_id' => (string) $validated['shop_id'],
            'customer_id' => $request->user()?->id,
            'vehicle_id' => (string) $validated['vehicle_id'],
            'service_id' => (string) $validated['service_id'],
            'shop_quote_id' => isset($validated['shop_quote_id']) ? (string) $validated['shop_quote_id'] : null,
            'idempotency_key' => $idempotencyKey,
            'preferred_date' => (string) $validated['preferred_date'],
            'preferred_time_slot' => (string) $validated['preferred_time_slot'],
            'customer_name' => (string) $validated['customer_name'],
            'customer_email' => (string) $validated['customer_email'],
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

    private function isAuthorizedForBooking(Request $request, Booking $booking): bool
    {
        $user = $request->user();

        if ($user instanceof User) {
            if ($user->role === UserRole::Admin) {
                return true;
            }

            if ($booking->customer_id !== null && $booking->customer_id === $user->id) {
                return true;
            }

            if (strtolower($booking->customer_email) === strtolower($user->email)) {
                return true;
            }

            if ($booking->shop !== null && $user->canAccessTenant($booking->shop)) {
                return true;
            }
        }

        $providedEmail = (string) ($request->input('customer_email') ?? $request->query('customer_email', ''));
        if ($providedEmail !== '' && strtolower($providedEmail) === strtolower($booking->customer_email)) {
            return true;
        }

        return false;
    }
}
