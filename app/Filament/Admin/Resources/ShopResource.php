<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ShopResource\Pages;
use App\Models\Shop;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

final class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Marketplace Operations';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Shop Information')
                    ->description('Core identity, contact details, and marketplace certification flags.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Shop Name')
                            ->required()
                            ->maxLength(180)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Directory Slug')
                            ->required()
                            ->maxLength(200)
                            ->unique(table: Shop::class, column: 'slug', ignoreRecord: true),

                        Forms\Components\TextInput::make('email')
                            ->label('Business Email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Business Phone')
                            ->tel()
                            ->required()
                            ->maxLength(30),

                        Forms\Components\TextInput::make('base_labor_rate')
                            ->label('Base Labor Rate ($/hr)')
                            ->numeric()
                            ->prefix('$')
                            ->suffix('/hr')
                            ->required()
                            ->minValue(0)
                            ->maxValue(9999.99)
                            ->step(0.01),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_certified')
                                    ->label('RepairPal Certified Shop')
                                    ->onColor('success')
                                    ->offColor('gray')
                                    ->default(false),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active in Public Directory')
                                    ->onColor('success')
                                    ->offColor('danger')
                                    ->default(false),
                            ]),
                    ]),

                Forms\Components\Section::make('Location & Coordinates')
                    ->description('US physical street address and geocoded coordinates for PostGIS radius search.')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('street')
                            ->label('Street Address')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->required()
                            ->maxLength(120),

                        Forms\Components\TextInput::make('state')
                            ->label('State (2-Letter USPS Code)')
                            ->required()
                            ->length(2)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn (string $state): string => strtoupper($state))
                            ->regex('/^[A-Za-z]{2}$/'),

                        Forms\Components\TextInput::make('zip_code')
                            ->label('ZIP Code')
                            ->required()
                            ->maxLength(10)
                            ->regex('/^[0-9]{5}(-[0-9]{4})?$/'),

                        Forms\Components\TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->required()
                            ->minValue(-90)
                            ->maxValue(90)
                            ->step(0.0000001),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->required()
                            ->minValue(-180)
                            ->maxValue(180)
                            ->step(0.0000001),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Shop Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Shop $record): string => $record->email),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->state(fn (Shop $record): string => "{$record->city}, {$record->state} {$record->zip_code}")
                    ->searchable(['city', 'state', 'zip_code'])
                    ->sortable(['state', 'city']),

                Tables\Columns\TextColumn::make('base_labor_rate')
                    ->label('Base Labor Rate')
                    ->money('USD')
                    ->suffix('/hr')
                    ->sortable(),

                Tables\Columns\TextColumn::make('is_certified')
                    ->label('Certification')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Certified' : 'Unverified')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-m-check-badge' : 'heroicon-m-clock')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('is_active')
                    ->label('Directory Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Onboarded')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_certified')
                    ->label('Certification Status')
                    ->trueLabel('Certified Shops')
                    ->falseLabel('Unverified Shops'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->trueLabel('Active Shops')
                    ->falseLabel('Inactive Shops'),

                Tables\Filters\SelectFilter::make('state')
                    ->label('US State')
                    ->options(fn (): array => Shop::query()
                        ->select('state')
                        ->distinct()
                        ->orderBy('state')
                        ->pluck('state', 'state')
                        ->all())
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('verify_shop')
                    ->label(fn (Shop $record): string => $record->is_certified ? 'Revoke Certification' : 'Verify Shop')
                    ->icon(fn (Shop $record): string => $record->is_certified ? 'heroicon-o-x-circle' : 'heroicon-o-check-badge')
                    ->color(fn (Shop $record): string => $record->is_certified ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Shop $record): string => $record->is_certified
                        ? "Revoke Certification for {$record->name}?"
                        : "Verify & Certify {$record->name}?")
                    ->modalDescription(fn (Shop $record): string => $record->is_certified
                        ? 'This will remove the Certified Partner badge from this shop in customer search results.'
                        : 'Confirm that business credentials, insurance, and technician certifications have been verified.')
                    ->action(function (Shop $record): void {
                        $newState = ! $record->is_certified;
                        $record->update(['is_certified' => $newState]);

                        Notification::make()
                            ->title($newState ? 'Shop Verified & Certified' : 'Shop Certification Revoked')
                            ->body("{$record->name} certification status is now " . ($newState ? 'Certified.' : 'Unverified.'))
                            ->color($newState ? 'success' : 'warning')
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShops::route('/'),
            'create' => Pages\CreateShop::route('/create'),
            'edit' => Pages\EditShop::route('/{record}/edit'),
        ];
    }
}
