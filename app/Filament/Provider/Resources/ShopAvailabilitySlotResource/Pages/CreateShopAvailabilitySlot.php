<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopAvailabilitySlotResource\Pages;

use App\Filament\Provider\Resources\ShopAvailabilitySlotResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateShopAvailabilitySlot extends CreateRecord
{
    protected static string $resource = ShopAvailabilitySlotResource::class;
}
