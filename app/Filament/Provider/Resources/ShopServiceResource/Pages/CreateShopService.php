<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopServiceResource\Pages;

use App\Filament\Provider\Resources\ShopServiceResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateShopService extends CreateRecord
{
    protected static string $resource = ShopServiceResource::class;
}
