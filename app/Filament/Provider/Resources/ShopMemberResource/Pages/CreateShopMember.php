<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopMemberResource\Pages;

use App\Filament\Provider\Resources\ShopMemberResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateShopMember extends CreateRecord
{
    protected static string $resource = ShopMemberResource::class;
}
