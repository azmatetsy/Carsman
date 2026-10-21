<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopAvailabilitySlotResource\Pages;

use App\Filament\Provider\Resources\ShopAvailabilitySlotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListShopAvailabilitySlots extends ListRecords
{
    protected static string $resource = ShopAvailabilitySlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
