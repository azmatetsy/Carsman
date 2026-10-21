<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopMemberResource\Pages;

use App\Enums\UserRole;
use App\Filament\Provider\Resources\ShopMemberResource;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateShopMember extends CreateRecord
{
    protected static string $resource = ShopMemberResource::class;

    /**
     * Provision or match the invited staff user by email without exposing cross-tenant user lists.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var Shop|null $tenant */
        $tenant = Filament::getTenant();
        $email = strtolower(trim((string) ($data['invite_email'] ?? '')));
        $name = trim((string) ($data['invite_name'] ?? 'Shop Team Member'));

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser !== null && ! in_array($existingUser->role, [UserRole::ShopOwner, UserRole::ShopStaff], true)) {
            throw ValidationException::withMessages([
                'data.invite_email' => 'This email belongs to a non-provider account and cannot be added as shop staff.',
            ]);
        }

        $user = $existingUser ?? User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(24)),
            'role' => UserRole::ShopStaff,
            'is_active' => true,
        ]);

        if ($tenant !== null) {
            $alreadyMember = ShopMember::withoutGlobalScopes()
                ->where('shop_id', $tenant->getKey())
                ->where('user_id', $user->getKey())
                ->exists();

            if ($alreadyMember) {
                throw ValidationException::withMessages([
                    'data.invite_email' => 'This user is already a team member of this shop.',
                ]);
            }
        }

        unset($data['invite_name'], $data['invite_email']);
        $data['user_id'] = $user->getKey();
        $data['joined_at'] = now();

        return $data;
    }
}
