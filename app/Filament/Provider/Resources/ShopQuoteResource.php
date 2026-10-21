<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources;

use App\Enums\QuoteStatus;
use App\Filament\Provider\Resources\ShopQuoteResource\Pages;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopQuote;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class ShopQuoteResource extends Resource
{
    protected static ?string $model = ShopQuote::class;

    protected static ?string $tenantOwnershipRelationshipName = 'shop';

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Customer Inquiries & Bookings';

    protected static ?string $navigationLabel = 'Shop Quotes';

    protected static ?string $modelLabel = 'Customer Quote';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return self::currentUserCanManageShop();
    }

    public static function canEdit(Model $record): bool
    {
        return self::currentUserCanManageShop();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function currentUserCanManageShop(): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        return $user instanceof User && $tenant instanceof Shop && $user->canManageShop($tenant);
    }

    public static function form(Form $form): Form
    {
        $recalculateTotal = static function (Get $get, Set $set): void {
            $labor = (float) ($get('labor_subtotal') ?? 0);
            $parts = (float) ($get('parts_subtotal') ?? 0);
            $fees = (float) ($get('shop_fees') ?? 0);
            $tax = (float) ($get('tax_estimate') ?? 0);

            $set('total_amount', number_format(max(0.0, $labor + $parts + $fees + $tax), 2, '.', ''));
        };

        return $form
            ->schema([
                Forms\Components\Section::make('Customer & Vehicle Inquiry')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('customer_email')
                            ->label('Customer Email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Customer Phone')
                            ->tel()
                            ->maxLength(30),

                        Forms\Components\Select::make('vehicle_id')
                            ->label('Vehicle')
                            ->options(fn (): array => Vehicle::query()
                                ->where('is_active', true)
                                ->orderByDesc('year')
                                ->orderBy('make')
                                ->limit(300)
                                ->get()
                                ->mapWithKeys(fn (Vehicle $v): array => [
                                    $v->id => "{$v->year} {$v->make} {$v->model} {$v->submodel}",
                                ])
                                ->all())
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('service_id')
                            ->label('Requested Service')
                            ->options(fn (): array => Service::query()
                                ->where('is_active', true)
                                ->orderBy('category')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Quote Status')
                            ->options(QuoteStatus::class)
                            ->default(QuoteStatus::Quoted)
                            ->native(false)
                            ->required(),

                        Forms\Components\Textarea::make('customer_issue_notes')
                            ->label('Customer Issue Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Itemized Shop Quote Breakdown')
                    ->columns(5)
                    ->schema([
                        Forms\Components\TextInput::make('labor_subtotal')
                            ->label('Labor ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated($recalculateTotal)
                            ->required(),

                        Forms\Components\TextInput::make('parts_subtotal')
                            ->label('OEM / Parts ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated($recalculateTotal)
                            ->required(),

                        Forms\Components\TextInput::make('shop_fees')
                            ->label('Shop Supplies ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated($recalculateTotal)
                            ->required(),

                        Forms\Components\TextInput::make('tax_estimate')
                            ->label('Est. Sales Tax ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated($recalculateTotal)
                            ->required(),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Quoted ($)')
                            ->numeric()
                            ->prefix('$')
                            ->readOnly()
                            ->dehydrated(true)
                            ->required(),

                        Forms\Components\DatePicker::make('valid_until')
                            ->label('Quote Valid Until')
                            ->default(fn (): string => now()->addDays(14)->toDateString())
                            ->native(false)
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('scope_notes')
                            ->label('Shop Warranty & Repair Scope Notes')
                            ->rows(3)
                            ->columnSpan(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (ShopQuote $record): string => $record->customer_email),

                Tables\Columns\TextColumn::make('vehicle.make')
                    ->label('Vehicle')
                    ->state(fn (ShopQuote $record): string => $record->vehicle
                        ? "{$record->vehicle->year} {$record->vehicle->make} {$record->vehicle->model}"
                        : '—'),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Repair Service')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Quoted')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('revision_number')
                    ->label('Rev #')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date('M d, Y'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(QuoteStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('publish_quote')
                    ->label('Issue Quote')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (ShopQuote $record): bool => $record->status === QuoteStatus::Requested && self::currentUserCanManageShop())
                    ->form([
                        Forms\Components\TextInput::make('labor_subtotal')
                            ->label('Labor Subtotal ($)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Forms\Components\TextInput::make('parts_subtotal')
                            ->label('Parts Subtotal ($)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Forms\Components\TextInput::make('shop_fees')
                            ->label('Shop Fees ($)')
                            ->numeric()
                            ->default(25.00)
                            ->minValue(0)
                            ->required(),
                        Forms\Components\TextInput::make('tax_estimate')
                            ->label('Estimated Tax ($)')
                            ->numeric()
                            ->default(0.00)
                            ->minValue(0)
                            ->required(),
                        Forms\Components\Textarea::make('scope_notes')
                            ->label('Repair Scope & Warranty Notes')
                            ->required(),
                    ])
                    ->action(function (ShopQuote $record, array $data): void {
                        $labor = (float) $data['labor_subtotal'];
                        $parts = (float) $data['parts_subtotal'];
                        $fees = (float) $data['shop_fees'];
                        $tax = (float) $data['tax_estimate'];
                        $total = round($labor + $parts + $fees + $tax, 2);

                        $record->update([
                            'status' => QuoteStatus::Quoted,
                            'labor_subtotal' => $labor,
                            'parts_subtotal' => $parts,
                            'shop_fees' => $fees,
                            'tax_estimate' => $tax,
                            'total_amount' => $total,
                            'scope_notes' => (string) $data['scope_notes'],
                            'revision_number' => $record->revision_number + 1,
                            'valid_until' => now()->addDays(14)->toDateString(),
                        ]);

                        Notification::make()
                            ->title('Quote Issued to Customer')
                            ->body("Total quote of \${$total} published (Rev #{$record->revision_number}).")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopQuotes::route('/'),
            'create' => Pages\CreateShopQuote::route('/create'),
            'edit' => Pages\EditShopQuote::route('/{record}/edit'),
        ];
    }
}
