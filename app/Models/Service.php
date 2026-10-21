<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Service extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'services';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category',
        'name',
        'slug',
        'description',
        'default_labor_hours',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_labor_hours' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Shop, $this>
     */
    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_services', 'service_id', 'shop_id')
            ->using(ShopService::class)
            ->withPivot(['id', 'pricing_type', 'hourly_rate', 'fixed_price', 'estimated_minutes', 'is_active'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ShopService, $this>
     */
    public function shopServices(): HasMany
    {
        return $this->hasMany(ShopService::class, 'service_id');
    }
}
