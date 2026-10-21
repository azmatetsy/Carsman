<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopVerificationDocumentResource\Pages;

use App\Filament\Provider\Resources\ShopVerificationDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListShopVerificationDocuments extends ListRecords
{
    protected static string $resource = ShopVerificationDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Upload Document'),
        ];
    }
}
