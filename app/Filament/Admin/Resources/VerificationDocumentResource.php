<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\VerificationDocumentResource\Pages;
use App\Models\ShopVerificationDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class VerificationDocumentResource extends Resource
{
    protected static ?string $model = ShopVerificationDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Marketplace Operations';

    protected static ?string $navigationLabel = 'Verification Queue';

    protected static ?string $modelLabel = 'Verification Document';

    protected static ?int $navigationSort = 20;

    /**
     * Bypass tenant global scope in the Super-Admin panel so admins can review documents across all shops.
     *
     * @return Builder<ShopVerificationDocument>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Compliance Document Review')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('shop_id')
                            ->relationship('shop', 'name')
                            ->label('Shop')
                            ->disabled(),

                        Forms\Components\TextInput::make('document_type')
                            ->label('Document Type')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Review Decision')
                            ->options([
                                'pending' => 'Pending Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\DatePicker::make('expires_at')
                            ->label('Document Expiration Date')
                            ->native(false),

                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Decision / Rejection Reason')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shop.name')
                    ->label('Shop')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Document Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'business_license' => 'Business License',
                        'garage_liability_insurance' => 'Garage Liability Insurance',
                        'ase_certification' => 'ASE Certification',
                        'epa_permit' => 'EPA / BAR Permit',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date('M d, Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Reviewed At')
                    ->dateTime('M d, Y H:i')
                    ->placeholder('Unreviewed'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve_document')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ShopVerificationDocument $record): bool => $record->status !== 'approved')
                    ->action(function (ShopVerificationDocument $record): void {
                        $record->update([
                            'status' => 'approved',
                            'rejection_reason' => null,
                            'reviewed_by_user_id' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Verification Document Approved')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject_document')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (ShopVerificationDocument $record, array $data): void {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => (string) $data['rejection_reason'],
                            'reviewed_by_user_id' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Verification Document Rejected')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVerificationDocuments::route('/'),
            'edit' => Pages\EditVerificationDocument::route('/{record}/edit'),
        ];
    }
}
