<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\VerificationDocumentResource\Pages;

use App\Filament\Admin\Resources\VerificationDocumentResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

final class EditVerificationDocument extends EditRecord
{
    protected static string $resource = VerificationDocumentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['reviewed_by_user_id'] = Auth::id();
        $data['reviewed_at'] = now();

        if (($data['status'] ?? null) === 'approved') {
            $data['rejection_reason'] = null;
        }

        return $data;
    }
}
