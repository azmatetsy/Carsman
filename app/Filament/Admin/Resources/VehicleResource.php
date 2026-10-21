<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\VehicleResource\Pages;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Catalog & Taxonomy';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Vehicle Taxonomy Specification')
                    ->description('Master Year / Make / Model / Submodel / Engine specification record.')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('year')
                            ->label('Model Year')
                            ->numeric()
                            ->required()
                            ->minValue(1980)
                            ->maxValue(2035),

                        Forms\Components\TextInput::make('make')
                            ->label('Make')
                            ->placeholder('e.g., Toyota')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('model')
                            ->label('Model')
                            ->placeholder('e.g., Camry')
                            ->required()
                            ->maxLength(120),

                        Forms\Components\TextInput::make('submodel')
                            ->label('Submodel / Trim')
                            ->placeholder('e.g., XSE V6')
                            ->default('')
                            ->maxLength(120),

                        Forms\Components\TextInput::make('engine')
                            ->label('Engine Specification')
                            ->placeholder('e.g., 3.5L V6 DOHC 24V')
                            ->default('')
                            ->maxLength(150),

                        Forms\Components\Select::make('fuel_type')
                            ->label('Fuel Type')
                            ->options([
                                'Gasoline' => 'Gasoline',
                                'Hybrid' => 'Hybrid',
                                'Plug-in Hybrid' => 'Plug-in Hybrid',
                                'Battery Electric (BEV)' => 'Battery Electric (BEV)',
                                'Diesel' => 'Diesel',
                                'Flex Fuel (E85)' => 'Flex Fuel (E85)',
                            ])
                            ->searchable(),

                        Forms\Components\TextInput::make('transmission')
                            ->label('Transmission')
                            ->maxLength(100),

                        Forms\Components\Select::make('drivetrain')
                            ->label('Drivetrain')
                            ->options([
                                'FWD' => 'FWD (Front-Wheel Drive)',
                                'RWD' => 'RWD (Rear-Wheel Drive)',
                                'AWD' => 'AWD (All-Wheel Drive)',
                                '4WD' => '4WD (Four-Wheel Drive)',
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active in Vehicle Selector')
                            ->default(true)
                            ->onColor('success'),
                    ]),

                Forms\Components\Section::make('NHTSA vPIC Reference IDs')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('nhtsa_make_id')
                            ->label('NHTSA Make ID')
                            ->numeric(),

                        Forms\Components\TextInput::make('nhtsa_model_id')
                            ->label('NHTSA Model ID')
                            ->numeric(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('year')
                    ->label('Year')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('make')
                    ->label('Make')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('model')
                    ->label('Model')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('submodel')
                    ->label('Submodel / Trim')
                    ->placeholder('Base')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('engine')
                    ->label('Engine')
                    ->placeholder('Standard')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('year', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('make')
                    ->label('Make')
                    ->options(fn (): array => Vehicle::query()
                        ->select('make')
                        ->distinct()
                        ->orderBy('make')
                        ->pluck('make', 'make')
                        ->all())
                    ->searchable()
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('year')
                    ->label('Model Year')
                    ->options(fn (): array => Vehicle::query()
                        ->select('year')
                        ->distinct()
                        ->orderByDesc('year')
                        ->pluck('year', 'year')
                        ->mapWithKeys(fn (int|string $year): array => [(string) $year => (string) $year])
                        ->all())
                    ->searchable()
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
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
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
