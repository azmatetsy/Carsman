<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CustomerVehicle;
use App\Models\EstimateRequest;
use App\Models\ShopQuote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerAccountController
{
    /**
     * GET /account
     * Render the customer portal with saved vehicles, estimates, shop quotes, and appointments (`AUTO-06-T05`).
     *
     * Prevents unauthenticated email enumeration (`[CRITICAL-02]`):
     * - Authenticated users see all records tied to their `user_id` or verified account `email`.
     * - Unauthenticated guests must supply BOTH their `email` AND the specific `booking_id` or `quote_id`
     *   UUID returned at submission time, scoping results strictly to that single transaction.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $guestEmail = strtolower(trim((string) $request->query('email', '')));
        $guestBookingId = trim((string) $request->query('booking_id', ''));
        $guestQuoteId = trim((string) $request->query('quote_id', ''));

        if ($user !== null) {
            $userEmail = strtolower($user->email);

            $savedVehicles = CustomerVehicle::query()
                ->where('user_id', $user->id)
                ->with('vehicle')
                ->orderByDesc('is_primary')
                ->get();

            $bookings = Booking::withoutGlobalScopes()
                ->where(static function (Builder $query) use ($user, $userEmail): void {
                    $query->where('customer_id', $user->id)
                        ->orWhereRaw('LOWER(customer_email) = ?', [$userEmail]);
                })
                ->with(['shop', 'vehicle', 'service'])
                ->orderByDesc('created_at')
                ->limit(25)
                ->get();

            $quotes = ShopQuote::withoutGlobalScopes()
                ->where(static function (Builder $query) use ($user, $userEmail): void {
                    $query->where('customer_id', $user->id)
                        ->orWhereRaw('LOWER(customer_email) = ?', [$userEmail]);
                })
                ->with(['shop', 'vehicle', 'service'])
                ->orderByDesc('created_at')
                ->limit(25)
                ->get();

            $estimates = EstimateRequest::query()
                ->where('customer_id', $user->id)
                ->with(['vehicle', 'service'])
                ->orderByDesc('created_at')
                ->limit(15)
                ->get();

            return Inertia::render('Account/Dashboard', [
                'customerEmail' => $user->email,
                'savedVehicles' => $savedVehicles,
                'bookings' => $bookings,
                'quotes' => $quotes,
                'estimates' => $estimates,
            ]);
        }

        // Guest lookup requires both email AND exact booking_id or quote_id UUID
        $bookings = ($guestEmail !== '' && preg_match('/^[0-9a-fA-F-]{36}$/', $guestBookingId) === 1)
            ? Booking::withoutGlobalScopes()
                ->whereKey($guestBookingId)
                ->whereNull('customer_id')
                ->whereRaw('LOWER(customer_email) = ?', [$guestEmail])
                ->with(['shop', 'vehicle', 'service'])
                ->get()
            : collect();

        $quotes = ($guestEmail !== '' && preg_match('/^[0-9a-fA-F-]{36}$/', $guestQuoteId) === 1)
            ? ShopQuote::withoutGlobalScopes()
                ->whereKey($guestQuoteId)
                ->whereNull('customer_id')
                ->whereRaw('LOWER(customer_email) = ?', [$guestEmail])
                ->with(['shop', 'vehicle', 'service'])
                ->get()
            : collect();

        return Inertia::render('Account/Dashboard', [
            'customerEmail' => $guestEmail !== '' ? $guestEmail : 'Guest Session',
            'savedVehicles' => [],
            'bookings' => $bookings,
            'quotes' => $quotes,
            'estimates' => [],
        ]);
    }
}
