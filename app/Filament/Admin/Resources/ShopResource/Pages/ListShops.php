<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShopResource\Pages;

use App\Filament\Admin\Resources\ShopResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListShops extends ListRecords
{
    protected static string $resource = ShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
