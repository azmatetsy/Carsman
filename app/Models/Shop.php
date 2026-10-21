<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\HasCurrentTenantLabel;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Shop extends Model implements HasCurrentTenantLabel, HasName
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'shops';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'street',
        'city',
        'state',
        'zip_code',
        'latitude',
        'longitude',
        'is_certified',
        'is_active',
        'base_labor_rate',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_certified' => 'boolean',
            'is_active' => 'boolean',
            'base_labor_rate' => 'decimal:2',
        ];
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getCurrentTenantLabel(): string
    {
        return $this->is_certified ? 'Certified Partner Shop' : 'Repair Shop';
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_members', 'shop_id', 'user_id')
            ->using(ShopMember::class)
            ->withPivot(['id', 'role', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ShopMember, $this>
     */
    public function shopMembers(): HasMany
    {
        return $this->hasMany(ShopMember::class, 'shop_id');
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'shop_services', 'shop_id', 'service_id')
            ->using(ShopService::class)
            ->withPivot(['id', 'pricing_type', 'hourly_rate', 'fixed_price', 'estimated_minutes', 'is_active'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ShopService, $this>
     */
    public function shopServices(): HasMany
    {
        return $this->hasMany(ShopService::class, 'shop_id');
    }

    /**
     * @return HasMany<ShopAvailabilitySlot, $this>
     */
    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(ShopAvailabilitySlot::class, 'shop_id');
    }

    /**
     * @return HasMany<ShopVerificationDocument, $this>
     */
    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(ShopVerificationDocument::class, 'shop_id');
    }

    /**
     * @return HasMany<ShopQuote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(ShopQuote::class, 'shop_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'shop_id');
    }
}
