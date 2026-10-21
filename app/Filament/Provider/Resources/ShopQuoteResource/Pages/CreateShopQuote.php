<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources\ShopQuoteResource\Pages;

use App\Filament\Provider\Resources\ShopQuoteResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateShopQuote extends CreateRecord
{
    protected static string $resource = ShopQuoteResource::class;

    /**
     * Deterministically compute total_amount on quote creation (`[CRITICAL-01]`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $labor = (float) ($data['labor_subtotal'] ?? 0);
        $parts = (float) ($data['parts_subtotal'] ?? 0);
        $fees = (float) ($data['shop_fees'] ?? 0);
        $tax = (float) ($data['tax_estimate'] ?? 0);

        $data['total_amount'] = round(max(0.0, $labor + $parts + $fees + $tax), 2);
        $data['revision_number'] = 1;

        return $data;
    }
}
