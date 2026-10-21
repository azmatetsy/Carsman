<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\ShopTenantScope;
use App\Models\Shop;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Enforces application-level multi-tenant isolation in tandem with PostgreSQL composite FKs & RLS.
 */
trait BelongsToShop
{
    public static function bootBelongsToShop(): void
    {
        static::addGlobalScope(new ShopTenantScope());

        static::creating(function ($model): void {
            if ($model->shop_id === null) {
                $tenant = Filament::getTenant();
                if ($tenant instanceof Shop) {
                    $model->shop_id = $tenant->getKey();
                }
            }

            if ($model->shop_id === null) {
                throw new RuntimeException('Tenant isolation violation: shop_id is required on tenant-scoped model [' . static::class . '].');
            }
        });

        static::updating(function ($model): void {
            if ($model->isDirty('shop_id')) {
                throw new RuntimeException('Tenant isolation violation: reassigning shop_id across tenants is prohibited.');
            }
        });
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
}
