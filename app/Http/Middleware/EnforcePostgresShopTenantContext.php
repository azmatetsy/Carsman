<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the current Filament Shop tenant UUID to the PostgreSQL session context
 * (`app.current_shop_id`) so PostgreSQL Row-Level Security (RLS) policies enforce
 * strict database-level tenant isolation across all queries, and resets the context
 * after the request completes to prevent connection-pool leakage.
 */
final class EnforcePostgresShopTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();
        $tenantId = $tenant !== null ? (string) $tenant->getKey() : '';

        config(['app.current_shop_tenant_id' => $tenantId !== '' ? $tenantId : null]);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT set_config(?, ?, false)', ['app.current_shop_id', $tenantId]);
        }

        try {
            return $next($request);
        } finally {
            config(['app.current_shop_tenant_id' => null]);

            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('SELECT set_config(?, ?, false)', ['app.current_shop_id', '']);
            }
        }
    }
}
