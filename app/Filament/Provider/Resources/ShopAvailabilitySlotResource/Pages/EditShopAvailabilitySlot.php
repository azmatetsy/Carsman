<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopAvailabilitySlotResource\Pages;

use App\Filament\Provider\Resources\ShopAvailabilitySlotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditShopAvailabilitySlot extends EditRecord
{
    protected static string $resource = ShopAvailabilitySlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
