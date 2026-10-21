<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopMemberResource\Pages;

use App\Filament\Provider\Resources\ShopMemberResource;
use App\Models\ShopMember;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

final class EditShopMember extends EditRecord
{
    protected static string $resource = ShopMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn (ShopMember $record): bool => $record->user_id === Auth::id()),
        ];
    }
}
