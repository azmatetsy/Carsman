<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\BookingResource\Pages;

use App\Filament\Provider\Resources\BookingResource;
use Filament\Resources\Pages\EditRecord;

final class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;
}
