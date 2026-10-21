<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CustomerVehicle;
use App\Models\EstimateRequest;
use App\Models\ShopQuote;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerAccountController
{
    /**
     * GET /account
     * Render the customer portal with saved vehicles, estimates, shop quotes, and appointments (`AUTO-06-T05`).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $lookupEmail = (string) ($user?->email ?? $request->query('email', 'customer@repairpal.test'));

        $savedVehicles = $user !== null
            ? CustomerVehicle::query()
                ->where('user_id', $user->id)
                ->with('vehicle')
                ->orderByDesc('is_primary')
                ->get()
            : collect();

        $bookings = Booking::withoutGlobalScopes()
            ->when(
                $user !== null,
                static fn ($q) => $q->where('customer_id', $user->id)->orWhere('customer_email', $lookupEmail),
                static fn ($q) => $q->where('customer_email', $lookupEmail),
            )
            ->with(['shop', 'vehicle', 'service'])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $quotes = ShopQuote::withoutGlobalScopes()
            ->when(
                $user !== null,
                static fn ($q) => $q->where('customer_id', $user->id)->orWhere('customer_email', $lookupEmail),
                static fn ($q) => $q->where('customer_email', $lookupEmail),
            )
            ->with(['shop', 'vehicle', 'service'])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $estimates = $user !== null
            ? EstimateRequest::query()
                ->where('customer_id', $user->id)
                ->with(['vehicle', 'service'])
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
            : collect();

        return Inertia::render('Account/Dashboard', [
            'customerEmail' => $lookupEmail,
            'savedVehicles' => $savedVehicles,
            'bookings' => $bookings,
            'quotes' => $quotes,
            'estimates' => $estimates,
        ]);
    }
}
