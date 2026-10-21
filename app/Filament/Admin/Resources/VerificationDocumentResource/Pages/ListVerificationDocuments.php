<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\VerificationDocumentResource\Pages;

use App\Filament\Admin\Resources\VerificationDocumentResource;
use Filament\Resources\Pages\ListRecords;

final class ListVerificationDocuments extends ListRecords
{
    protected static string $resource = VerificationDocumentResource::class;
}
