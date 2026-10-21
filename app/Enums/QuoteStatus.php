<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuoteStatus: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Quoted = 'quoted';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'Quote Requested',
            self::Quoted => 'Quoted by Shop',
            self::Accepted => 'Accepted by Customer',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Quoted => 'info',
            self::Accepted => 'success',
            self::Declined => 'danger',
            self::Expired => 'gray',
        };
    }
}
