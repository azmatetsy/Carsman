<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopVerificationDocumentResource\Pages;

use App\Filament\Provider\Resources\ShopVerificationDocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

final class CreateShopVerificationDocument extends CreateRecord
{
    protected static string $resource = ShopVerificationDocumentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by_user_id'] = Auth::id();
        $data['status'] = 'pending';

        return $data;
    }
}
