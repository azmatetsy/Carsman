<?php

declare(strict_types=1);

namespace App\Enums;

enum ShopMemberRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Technician = 'technician';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canManageShopSettings(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }
}
