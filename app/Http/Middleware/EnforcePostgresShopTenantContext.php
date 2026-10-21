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
 * strict database-level tenant isolation across all queries.
 */
final class EnforcePostgresShopTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant !== null) {
            $tenantId = (string) $tenant->getKey();
            config(['app.current_shop_tenant_id' => $tenantId]);

            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('SELECT set_config(?, ?, false)', ['app.current_shop_id', $tenantId]);
            }
        }

        return $next($request);
    }
}
