<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\VerificationDocumentResource\Pages;

use App\Filament\Admin\Resources\VerificationDocumentResource;
use Filament\Resources\Pages\EditRecord;

final class EditVerificationDocument extends EditRecord
{
    protected static string $resource = VerificationDocumentResource::class;
}
