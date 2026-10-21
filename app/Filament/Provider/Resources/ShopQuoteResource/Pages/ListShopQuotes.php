<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopQuoteResource\Pages;

use App\Filament\Provider\Resources\ShopQuoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListShopQuotes extends ListRecords
{
    protected static string $resource = ShopQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
