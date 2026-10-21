<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopServiceResource\Pages;

use App\Filament\Provider\Resources\ShopServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListShopServices extends ListRecords
{
    protected static string $resource = ShopServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
