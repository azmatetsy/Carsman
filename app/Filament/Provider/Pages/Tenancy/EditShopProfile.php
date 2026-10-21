<?php

declare(strict_types=1);

namespace App\Filament\Provider\Pages\Tenancy;

use App\Enums\ShopMemberRole;
use App\Models\Shop;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class EditShopProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Shop Profile & Location';
    }

    public static function canView(Model $tenant): bool
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $tenant instanceof Shop) {
            return false;
        }

        return $user->hasShopRole($tenant, ShopMemberRole::Owner)
            || $user->hasShopRole($tenant, ShopMemberRole::Manager);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Business Profile & Labor Rate')
                    ->description('Update public contact details and standard hourly labor rate for your repair shop.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Shop Name')
                            ->required()
                            ->maxLength(180),

                        Forms\Components\TextInput::make('slug')
                            ->label('Public Directory Slug')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('email')
                            ->label('Public Contact Email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Public Phone Number')
                            ->tel()
                            ->required()
                            ->maxLength(30),

                        Forms\Components\TextInput::make('base_labor_rate')
                            ->label('Standard Base Labor Rate ($/hr)')
                            ->numeric()
                            ->prefix('$')
                            ->suffix('/hr')
                            ->required()
                            ->minValue(0)
                            ->maxValue(9999.99)
                            ->step(0.01),
                    ]),

                Forms\Components\Section::make('Physical Location & Coordinates')
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
                            ->label('State (2-Letter)')
                            ->required()
                            ->length(2)
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
}
