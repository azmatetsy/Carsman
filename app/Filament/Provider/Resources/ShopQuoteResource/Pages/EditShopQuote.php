<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopQuoteResource\Pages;

use App\Filament\Provider\Resources\ShopQuoteResource;
use Filament\Resources\Pages\EditRecord;

final class EditShopQuote extends EditRecord
{
    protected static string $resource = ShopQuoteResource::class;

    /**
     * Increment revision_number whenever pricing or scope changes on an existing quote (`AUTO-05-T07`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $labor = (float) ($data['labor_subtotal'] ?? 0);
        $parts = (float) ($data['parts_subtotal'] ?? 0);
        $fees = (float) ($data['shop_fees'] ?? 0);
        $tax = (float) ($data['tax_estimate'] ?? 0);

        $data['total_amount'] = round($labor + $parts + $fees + $tax, 2);
        $data['revision_number'] = ((int) $this->record->getAttribute('revision_number')) + 1;

        return $data;
    }
}
