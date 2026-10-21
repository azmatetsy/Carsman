<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources;

use App\Enums\ShopMemberRole;
use App\Enums\UserRole;
use App\Filament\Provider\Resources\ShopMemberResource\Pages;
use App\Models\ShopMember;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

final class ShopMemberResource extends Resource
{
    protected static ?string $model = ShopMember::class;

    protected static ?string $tenantOwnershipRelationshipName = 'shop';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Shop Operations';

    protected static ?string $navigationLabel = 'Team Members';

    protected static ?string $modelLabel = 'Team Member';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Shop Team Membership')
                    ->description('Assign shop owners, managers, and technicians to this repair shop tenant.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Platform User')
                            ->options(fn (): array => User::query()
                                ->where('is_active', true)
                                ->whereIn('role', [UserRole::ShopOwner->value, UserRole::ShopStaff->value])
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [
                                    $user->id => "{$user->name} ({$user->email})",
                                ])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->unique(
                                table: ShopMember::class,
                                column: 'user_id',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where(
                                    'shop_id',
                                    Filament::getTenant()?->getKey(),
                                ),
                            ),

                        Forms\Components\Select::make('role')
                            ->label('Shop Tenant Role')
                            ->options([
                                ShopMemberRole::Owner->value => 'Owner (Full Shop Control)',
                                ShopMemberRole::Manager->value => 'Service Manager (Quotes & Bookings)',
                                ShopMemberRole::Technician->value => 'Technician (Assigned Jobs)',
                            ])
                            ->default(ShopMemberRole::Technician->value)
                            ->native(false)
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Membership')
                            ->default(true)
                            ->onColor('success'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Tenant Role')
                    ->badge()
                    ->formatStateUsing(fn (ShopMemberRole|string $state): string => match ($state instanceof ShopMemberRole ? $state : ShopMemberRole::from((string) $state)) {
                        ShopMemberRole::Owner => 'Owner',
                        ShopMemberRole::Manager => 'Manager',
                        ShopMemberRole::Technician => 'Technician',
                    })
                    ->color(fn (ShopMemberRole|string $state): string => match ($state instanceof ShopMemberRole ? $state : ShopMemberRole::from((string) $state)) {
                        ShopMemberRole::Owner => 'danger',
                        ShopMemberRole::Manager => 'warning',
                        ShopMemberRole::Technician => 'info',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('joined_at')
                    ->label('Joined Shop')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopMembers::route('/'),
            'create' => Pages\CreateShopMember::route('/create'),
            'edit' => Pages\EditShopMember::route('/{record}/edit'),
        ];
    }
}
