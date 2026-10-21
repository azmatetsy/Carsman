<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShopResource\Pages;

use App\Filament\Admin\Resources\ShopResource;
use App\Models\Shop;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verify_shop')
                ->label(fn (Shop $record): string => $record->is_certified ? 'Revoke Certification' : 'Verify Shop')
                ->icon(fn (Shop $record): string => $record->is_certified ? 'heroicon-o-x-circle' : 'heroicon-o-check-badge')
                ->color(fn (Shop $record): string => $record->is_certified ? 'warning' : 'success')
                ->requiresConfirmation()
                ->action(function (Shop $record): void {
                    $newState = ! $record->is_certified;
                    $record->update(['is_certified' => $newState]);
                    $this->refreshFormData(['is_certified']);

                    Notification::make()
                        ->title($newState ? 'Shop Verified & Certified' : 'Shop Certification Revoked')
                        ->color($newState ? 'success' : 'warning')
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
