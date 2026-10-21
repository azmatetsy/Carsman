<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use RuntimeException;

final class ShopTenantScope implements Scope
{
    /**
     * Apply the tenant isolation scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = Filament::getTenant();

        if ($tenant !== null) {
            $builder->where($model->qualifyColumn('shop_id'), $tenant->getKey());

            return;
        }

        $configuredTenantId = config('app.current_shop_tenant_id');

        if ($configuredTenantId !== null) {
            $builder->where($model->qualifyColumn('shop_id'), $configuredTenantId);
        }
    }
}
