<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Provider\Pages\Tenancy\EditShopProfile;
use App\Filament\Provider\Pages\Tenancy\RegisterShop;
use App\Http\Middleware\EnforcePostgresShopTenantContext;
use App\Models\Shop;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class ProviderPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('provider')
            ->path('provider')
            ->login()
            ->authGuard('web')
            ->brandName('RepairPal Partner Portal')
            ->colors([
                'primary' => Color::Emerald,
                'danger' => Color::Red,
                'warning' => Color::Amber,
                'info' => Color::Blue,
            ])
            ->tenant(
                model: Shop::class,
                slugAttribute: 'slug',
                ownershipRelationship: 'shop',
            )
            ->tenantRegistration(RegisterShop::class)
            ->tenantProfile(EditShopProfile::class)
            ->tenantRoutePrefix('shops')
            ->tenantMiddleware([
                EnforcePostgresShopTenantContext::class,
            ], isPersistent: true)
            ->discoverResources(
                in: app_path('Filament/Provider/Resources'),
                for: 'App\\Filament\\Provider\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Provider/Pages'),
                for: 'App\\Filament\\Provider\\Pages',
            )
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Provider/Widgets'),
                for: 'App\\Filament\\Provider\\Widgets',
            )
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->databaseNotifications()
            ->spa();
    }
}
