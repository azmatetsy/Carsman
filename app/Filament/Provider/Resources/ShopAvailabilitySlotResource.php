<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\ShopAvailabilitySlotResource\Pages;
use App\Models\ShopAvailabilitySlot;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

final class ShopAvailabilitySlotResource extends Resource
{
    protected static ?string $model = ShopAvailabilitySlot::class;

    protected static ?string $tenantOwnershipRelationshipName = 'shop';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Shop Operations';

    protected static ?string $navigationLabel = 'Working Hours & Capacity';

    protected static ?string $modelLabel = 'Availability Day';

    protected static ?int $navigationSort = 30;

    /**
     * @return array<int, string>
     */
    public static function daysOfWeek(): array
    {
        return [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Daily Operating Hours & Booking Capacity')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('day_of_week')
                            ->label('Day of Week')
                            ->options(self::daysOfWeek())
                            ->native(false)
                            ->required()
                            ->unique(
                                table: ShopAvailabilitySlot::class,
                                column: 'day_of_week',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where(
                                    'shop_id',
                                    Filament::getTenant()?->getKey(),
                                ),
                            ),

                        Forms\Components\TextInput::make('max_concurrent_bookings')
                            ->label('Max Concurrent Bay Bookings')
                            ->numeric()
                            ->default(2)
                            ->minValue(1)
                            ->maxValue(100)
                            ->required(),

                        Forms\Components\TimePicker::make('opens_at')
                            ->label('Opens At')
                            ->seconds(false)
                            ->default('08:00')
                            ->required(),

                        Forms\Components\TimePicker::make('closes_at')
                            ->label('Closes At')
                            ->seconds(false)
                            ->default('17:30')
                            ->after('opens_at')
                            ->required(),

                        Forms\Components\Toggle::make('is_closed')
                            ->label('Shop Closed on This Day')
                            ->default(false)
                            ->onColor('danger'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Day')
                    ->formatStateUsing(fn (int $state): string => self::daysOfWeek()[$state] ?? (string) $state)
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('opens_at')
                    ->label('Opens')
                    ->time('g:i A'),

                Tables\Columns\TextColumn::make('closes_at')
                    ->label('Closes')
                    ->time('g:i A'),

                Tables\Columns\TextColumn::make('max_concurrent_bookings')
                    ->label('Max Concurrent Bays')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_closed')
                    ->label('Closed')
                    ->boolean(),
            ])
            ->defaultSort('day_of_week')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopAvailabilitySlots::route('/'),
            'create' => Pages\CreateShopAvailabilitySlot::route('/create'),
            'edit' => Pages\EditShopAvailabilitySlot::route('/{record}/edit'),
        ];
    }
}
