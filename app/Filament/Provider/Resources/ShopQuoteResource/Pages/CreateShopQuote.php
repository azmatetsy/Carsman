<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopQuoteResource\Pages;

use App\Filament\Provider\Resources\ShopQuoteResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateShopQuote extends CreateRecord
{
    protected static string $resource = ShopQuoteResource::class;
}
