<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Booking;
use App\Models\Shop;
use App\Models\ShopVerificationDocument;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class AdminOverviewStats extends BaseWidget
{
    protected function getStats(): array
    {
        $certifiedShops = Shop::query()
            ->where('is_active', true)
            ->where('is_certified', true)
            ->count();

        $pendingVerifications = ShopVerificationDocument::withoutGlobalScopes()
            ->where('status', 'pending')
            ->count();

        $totalBookings = Booking::withoutGlobalScopes()->count();

        $activeUsers = User::query()->where('is_active', true)->count();

        return [
            Stat::make('Certified Active Shops', (string) $certifiedShops)
                ->description('Live in public US directory')
                ->color('success'),

            Stat::make('Pending Document Reviews', (string) $pendingVerifications)
                ->description('Awaiting compliance sign-off')
                ->color($pendingVerifications > 0 ? 'warning' : 'success'),

            Stat::make('Marketplace Bookings', (string) $totalBookings)
                ->description('Customer appointment requests')
                ->color('info'),

            Stat::make('Active Platform Accounts', (string) $activeUsers)
                ->description('Customers, shop owners & staff')
                ->color('primary'),
        ];
    }
}
