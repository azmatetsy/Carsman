<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\VehicleResource\Pages;

use App\Filament\Admin\Resources\VehicleResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;
}
