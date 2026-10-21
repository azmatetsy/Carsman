<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopServiceResource\Pages;

use App\Filament\Provider\Resources\ShopServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditShopService extends EditRecord
{
    protected static string $resource = ShopServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
