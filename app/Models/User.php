<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShopMemberRole;
use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'mfa_enabled',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'mfa_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Shop, $this>
     */
    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_members', 'user_id', 'shop_id')
            ->using(ShopMember::class)
            ->withPivot(['id', 'role', 'is_active', 'joined_at'])
            ->wherePivot('is_active', true)
            ->withTimestamps();
    }

    /**
     * @return HasMany<ShopMember, $this>
     */
    public function shopMemberships(): HasMany
    {
        return $this->hasMany(ShopMember::class, 'user_id');
    }

    /**
     * Determine whether the user can access a specific isolated Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->role === UserRole::Admin,
            'provider' => $this->role->canAccessProviderPanel(),
            default => false,
        };
    }

    /**
     * Return all active Shop tenants this user is authorized to access in the `/provider` panel.
     *
     * @return Collection<int, Shop>
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($panel->getId() !== 'provider' || ! $this->is_active) {
            return new Collection();
        }

        return $this->shops()
            ->where('shops.is_active', true)
            ->get();
    }

    /**
     * Verify tenant boundary authorization for a given Shop model.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Shop || ! $this->is_active || ! $tenant->is_active) {
            return false;
        }

        return $this->shops()
            ->whereKey($tenant->getKey())
            ->where('shops.is_active', true)
            ->exists();
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->getTenants($panel)->first();
    }

    public function hasShopRole(Shop $shop, ShopMemberRole $role): bool
    {
        return $this->shopMemberships()
            ->withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('role', $role->value)
            ->where('is_active', true)
            ->exists();
    }

    public function canManageShop(Shop $shop): bool
    {
        return $this->shopMemberships()
            ->withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->whereIn('role', [ShopMemberRole::Owner->value, ShopMemberRole::Manager->value])
            ->where('is_active', true)
            ->exists();
    }
}
