<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopMemberResource\Pages;

use App\Filament\Provider\Resources\ShopMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditShopMember extends EditRecord
{
    protected static string $resource = ShopMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
