<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServiceDifficultyLevel: string implements HasColor, HasLabel
{
    case Easy = 'easy';
    case Moderate = 'moderate';
    case Complex = 'complex';
    case Master = 'master';

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
            self::Easy => 'Easy (Routine Maintenance)',
            self::Moderate => 'Moderate (Standard Repair)',
            self::Complex => 'Complex (Advanced Diagnostics)',
            self::Master => 'Master Tech (Powertrain / Specialty)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Easy => 'success',
            self::Moderate => 'info',
            self::Complex => 'warning',
            self::Master => 'danger',
        };
    }
}
