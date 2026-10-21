<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources;

use App\Enums\ShopMemberRole;
use App\Filament\Provider\Resources\ShopMemberResource\Pages;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class ShopMemberResource extends Resource
{
    protected static ?string $model = ShopMember::class;

    protected static ?string $tenantOwnershipRelationshipName = 'shop';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Shop Operations';

    protected static ?string $navigationLabel = 'Team Members';

    protected static ?string $modelLabel = 'Team Member';

    protected static ?int $navigationSort = 20;

    public static function canViewAny(): bool
    {
        return self::currentUserCanManageShop();
    }

    public static function canCreate(): bool
    {
        return self::currentUserCanManageShop();
    }

    public static function canEdit(Model $record): bool
    {
        if (! self::currentUserCanManageShop() || ! $record instanceof ShopMember) {
            return false;
        }

        // Only a Shop Owner can edit another Owner's membership record
        if ($record->role === ShopMemberRole::Owner) {
            $user = Auth::user();
            $tenant = Filament::getTenant();

            return $user instanceof User
                && $tenant instanceof Shop
                && $user->hasShopRole($tenant, ShopMemberRole::Owner);
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof ShopMember || $record->user_id === Auth::id()) {
            return false;
        }

        $user = Auth::user();
        $tenant = Filament::getTenant();

        return $user instanceof User
            && $tenant instanceof Shop
            && $user->hasShopRole($tenant, ShopMemberRole::Owner);
    }

    private static function currentUserCanManageShop(): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        return $user instanceof User && $tenant instanceof Shop && $user->canManageShop($tenant);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Shop Team Invitation & Role Assignment')
                    ->description('Invite a team member by their exact work email without exposing other shops on the platform.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('invite_name')
                            ->label('Team Member Full Name')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(150),

                        Forms\Components\TextInput::make('invite_email')
                            ->label('Team Member Work Email')
                            ->email()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255)
                            ->helperText('If no account exists for this email, a new shop staff account will be provisioned.'),

                        Forms\Components\Placeholder::make('member_identity')
                            ->label('Team Member')
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->content(fn (?ShopMember $record): string => $record?->user
                                ? "{$record->user->name} ({$record->user->email})"
                                : '—'),

                        Forms\Components\Select::make('role')
                            ->label('Shop Tenant Role')
                            ->options(function (): array {
                                $user = Auth::user();
                                $tenant = Filament::getTenant();
                                $isOwner = $user instanceof User
                                    && $tenant instanceof Shop
                                    && $user->hasShopRole($tenant, ShopMemberRole::Owner);

                                $roles = [
                                    ShopMemberRole::Manager->value => 'Service Manager (Quotes & Bookings)',
                                    ShopMemberRole::Technician->value => 'Technician (Assigned Jobs)',
                                ];

                                if ($isOwner) {
                                    $roles = [
                                        ShopMemberRole::Owner->value => 'Owner (Full Shop Control)',
                                        ...$roles,
                                    ];
                                }

                                return $roles;
                            })
                            ->default(ShopMemberRole::Technician->value)
                            ->disabled(fn (?ShopMember $record): bool => $record !== null && $record->user_id === Auth::id())
                            ->native(false)
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Membership')
                            ->default(true)
                            ->disabled(fn (?ShopMember $record): bool => $record !== null && $record->user_id === Auth::id())
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
