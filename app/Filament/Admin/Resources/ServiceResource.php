<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\ServiceDifficultyLevel;
use App\Filament\Admin\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

final class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Catalog & Taxonomy';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Standard automotive service taxonomy categories.
     *
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'Brakes' => 'Brakes',
            'Engine & Powertrain' => 'Engine & Powertrain',
            'Electrical & Battery' => 'Electrical & Battery',
            'Heating & Air Conditioning' => 'Heating & Air Conditioning',
            'Maintenance & Fluids' => 'Maintenance & Fluids',
            'Steering & Suspension' => 'Steering & Suspension',
            'Transmission & Drivetrain' => 'Transmission & Drivetrain',
            'Diagnostics & Inspection' => 'Diagnostics & Inspection',
            'Exhaust & Emissions' => 'Exhaust & Emissions',
            'Tires & Alignment' => 'Tires & Alignment',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Repair Service Catalog Entry')
                    ->description('Define standardized repair services available for shop rate mapping and customer search.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Service Name')
                            ->placeholder('e.g., Front Brake Pad Replacement')
                            ->required()
                            ->maxLength(180)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('URL Slug')
                            ->required()
                            ->maxLength(200)
                            ->unique(table: Service::class, column: 'slug', ignoreRecord: true),

                        Forms\Components\Select::make('category')
                            ->label('Service Category')
                            ->options(self::categories())
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('difficulty_level')
                            ->label('Difficulty Level')
                            ->options(ServiceDifficultyLevel::class)
                            ->default(ServiceDifficultyLevel::Moderate)
                            ->native(false)
                            ->required(),

                        Forms\Components\TextInput::make('default_labor_hours')
                            ->label('Default Standard Labor Hours')
                            ->numeric()
                            ->suffix('hrs')
                            ->minValue(0.1)
                            ->maxValue(200)
                            ->step(0.1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Published in Catalog')
                            ->default(true)
                            ->onColor('success'),

                        Forms\Components\Textarea::make('description')
                            ->label('Consumer Description & Scope')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Service Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('difficulty_level')
                    ->label('Difficulty')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('default_labor_hours')
                    ->label('Std. Labor')
                    ->suffix(' hrs')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('category')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options(self::categories())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('difficulty_level')
                    ->label('Difficulty Level')
                    ->options(ServiceDifficultyLevel::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Published Status'),
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
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
