<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Rescheduled = 'rescheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

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
            self::Requested => 'Requested',
            self::Confirmed => 'Confirmed',
            self::Rescheduled => 'Alternate Time Proposed',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Declined => 'Declined by Shop',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Confirmed => 'success',
            self::Rescheduled => 'info',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Declined, self::Cancelled => 'danger',
        };
    }

    /**
     * Enforce explicit booking state machine transitions (`AUTO-06-T02`).
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::Requested => in_array($target, [
                self::Confirmed,
                self::Rescheduled,
                self::Declined,
                self::Cancelled,
            ], true),
            self::Rescheduled => in_array($target, [
                self::Confirmed,
                self::Declined,
                self::Cancelled,
            ], true),
            self::Confirmed => in_array($target, [
                self::InProgress,
                self::Rescheduled,
                self::Cancelled,
            ], true),
            self::InProgress => in_array($target, [
                self::Completed,
            ], true),
            self::Completed, self::Declined, self::Cancelled => false,
        };
    }

    public function isCustomerCancellable(): bool
    {
        return in_array($this, [
            self::Requested,
            self::Confirmed,
            self::Rescheduled,
        ], true);
    }
}
