<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\BookingResource\Pages;

use App\Filament\Provider\Resources\BookingResource;
use Filament\Resources\Pages\ListRecords;

final class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;
}
