<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case ShopOwner = 'shop_owner';
    case ShopStaff = 'shop_staff';
    case Admin = 'admin';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canAccessAdminPanel(): bool
    {
        return $this === self::Admin;
    }

    public function canAccessProviderPanel(): bool
    {
        return in_array($this, [self::ShopOwner, self::ShopStaff], true);
    }
}
