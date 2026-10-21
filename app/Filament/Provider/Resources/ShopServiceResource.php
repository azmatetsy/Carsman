<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\ShopServiceResource\Pages;
use App\Models\Service;
use App\Models\ShopService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

final class ShopServiceResource extends Resource
{
    protected static ?string $model = ShopService::class;

    protected static ?string $tenantOwnershipRelationshipName = 'shop';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Shop Operations';

    protected static ?string $navigationLabel = 'Services & Pricing';

    protected static ?string $modelLabel = 'Shop Service Rate';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Service Rate & Pricing Configuration')
                    ->description('Configure whether this repair service uses your custom hourly rate, a fixed package price, or requires a custom shop quote.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('service_id')
                            ->label('Catalog Repair Service')
                            ->options(fn (): array => Service::query()
                                ->where('is_active', true)
                                ->orderBy('category')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Service $service): array => [
                                    $service->id => "{$service->category} — {$service->name}",
                                ])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->unique(
                                table: ShopService::class,
                                column: 'service_id',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where(
                                    'shop_id',
                                    Filament::getTenant()?->getKey(),
                                ),
                            ),

                        Forms\Components\Select::make('pricing_type')
                            ->label('Pricing Mode')
                            ->options([
                                'hourly' => 'Hourly Labor Rate',
                                'fixed' => 'Fixed Flat-Rate Package',
                                'quote_only' => 'Custom Quote Required (No Upfront Price)',
                            ])
                            ->default('hourly')
                            ->live()
                            ->native(false)
                            ->required(),

                        Forms\Components\TextInput::make('hourly_rate')
                            ->label('Custom Hourly Labor Rate ($/hr)')
                            ->numeric()
                            ->prefix('$')
                            ->suffix('/hr')
                            ->minValue(0)
                            ->maxValue(9999.99)
                            ->step(0.01)
                            ->visible(fn (Get $get): bool => $get('pricing_type') === 'hourly')
                            ->dehydratedWhenHidden(false)
                            ->helperText('Leave blank to fall back to your shop base labor rate.'),

                        Forms\Components\TextInput::make('fixed_price')
                            ->label('Fixed Package Price ($)')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0)
                            ->maxValue(999999.99)
                            ->step(0.01)
                            ->visible(fn (Get $get): bool => $get('pricing_type') === 'fixed')
                            ->required(fn (Get $get): bool => $get('pricing_type') === 'fixed')
                            ->dehydratedWhenHidden(false),

                        Forms\Components\TextInput::make('estimated_minutes')
                            ->label('Estimated Service Duration (Minutes)')
                            ->numeric()
                            ->suffix('mins')
                            ->minValue(15)
                            ->maxValue(14400)
                            ->step(15),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Offered by Shop')
                            ->default(true)
                            ->onColor('success'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Repair Service')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('service.category')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pricing_type')
                    ->label('Pricing Mode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'hourly' => 'Hourly Labor',
                        'fixed' => 'Fixed Price',
                        'quote_only' => 'Quote Only',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'hourly' => 'info',
                        'fixed' => 'success',
                        'quote_only' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('hourly_rate')
                    ->label('Hourly Rate')
                    ->money('USD')
                    ->suffix('/hr')
                    ->placeholder('Shop Base Rate'),

                Tables\Columns\TextColumn::make('fixed_price')
                    ->label('Fixed Price')
                    ->money('USD')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('estimated_minutes')
                    ->label('Est. Duration')
                    ->suffix(' mins')
                    ->placeholder('Standard'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pricing_type')
                    ->options([
                        'hourly' => 'Hourly Labor',
                        'fixed' => 'Fixed Price',
                        'quote_only' => 'Quote Only',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopServices::route('/'),
            'create' => Pages\CreateShopService::route('/create'),
            'edit' => Pages\EditShopService::route('/{record}/edit'),
        ];
    }
}
