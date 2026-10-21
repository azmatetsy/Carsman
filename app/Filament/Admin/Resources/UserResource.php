<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Identity & Access';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Account Details')
                    ->description('Manage user identity, contact details, and credentials.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(table: User::class, column: 'email', ignoreRecord: true),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(30),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->minLength(8)
                            ->maxLength(255)
                            ->helperText('Leave blank when editing to keep the current password.'),
                    ]),

                Forms\Components\Section::make('Role & Account Status')
                    ->description('Control platform role assignment and account suspension status.')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Platform Role')
                            ->required()
                            ->options([
                                UserRole::Customer->value => 'Customer',
                                UserRole::ShopOwner->value => 'Shop Owner',
                                UserRole::ShopStaff->value => 'Shop Staff',
                                UserRole::Admin->value => 'Platform Admin',
                            ])
                            ->default(UserRole::Customer->value)
                            ->native(false)
                            ->disabled(fn (?User $record): bool => $record !== null && $record->getKey() === Auth::id())
                            ->helperText('Self-demotion is disabled for the currently signed-in administrator.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Account Active (Not Suspended)')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->disabled(fn (?User $record): bool => $record !== null && $record->getKey() === Auth::id())
                            ->helperText('Turn off to immediately suspend sign-in and panel access.'),

                        Forms\Components\Toggle::make('mfa_enabled')
                            ->label('MFA Enforced')
                            ->default(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (UserRole|string $state): string => match ($state instanceof UserRole ? $state : UserRole::from((string) $state)) {
                        UserRole::Admin => 'Admin',
                        UserRole::ShopOwner => 'Shop Owner',
                        UserRole::ShopStaff => 'Shop Staff',
                        UserRole::Customer => 'Customer',
                    })
                    ->color(fn (UserRole|string $state): string => match ($state instanceof UserRole ? $state : UserRole::from((string) $state)) {
                        UserRole::Admin => 'danger',
                        UserRole::ShopOwner => 'success',
                        UserRole::ShopStaff => 'info',
                        UserRole::Customer => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Suspended')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined Date')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        UserRole::Customer->value => 'Customer',
                        UserRole::ShopOwner->value => 'Shop Owner',
                        UserRole::ShopStaff->value => 'Shop Staff',
                        UserRole::Admin->value => 'Platform Admin',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->trueLabel('Active Users')
                    ->falseLabel('Suspended Users')
                    ->placeholder('All Users'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('toggle_suspension')
                    ->label(fn (User $record): string => $record->is_active ? 'Suspend' : 'Reactivate')
                    ->icon(fn (User $record): string => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->hidden(fn (User $record): bool => $record->getKey() === Auth::id())
                    ->action(function (User $record): void {
                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title($record->is_active ? 'User account reactivated' : 'User account suspended')
                            ->body("Account status updated for {$record->email}.")
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
