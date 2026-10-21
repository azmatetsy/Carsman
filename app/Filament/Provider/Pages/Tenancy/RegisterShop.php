<?php

declare(strict_types=1);

namespace App\Filament\Provider\Pages\Tenancy;

use App\Enums\ShopMemberRole;
use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Pages\Tenancy\RegisterTenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegisterShop extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register New Repair Shop';
    }

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->is_active
            && $user->role === UserRole::ShopOwner;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Shop Application & Identity')
                    ->description('New shops start in pending verification status until approved by platform administrators.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Legal / DBA Shop Name')
                            ->required()
                            ->maxLength(180)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (filled($state)) {
                                    $set('slug', Str::slug($state) . '-' . Str::lower(Str::random(4)));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Shop URL Slug')
                            ->required()
                            ->maxLength(200)
                            ->unique(table: Shop::class, column: 'slug'),

                        Forms\Components\TextInput::make('email')
                            ->label('Shop Dispatch / Customer Email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Shop Phone Number')
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

                Forms\Components\Section::make('US Shop Address & Coordinates')
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
                            ->label('US State (2-Letter)')
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
                            ->default(37.7749295)
                            ->required()
                            ->minValue(-90)
                            ->maxValue(90),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->default(-122.4194155)
                            ->required()
                            ->minValue(-180)
                            ->maxValue(180),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Shop
    {
        return DB::transaction(function () use ($data): Shop {
            /** @var Shop $shop */
            $shop = Shop::query()->create([
                ...$data,
                'is_certified' => false,
                'is_active' => true,
            ]);

            /** @var User|null $user */
            $user = Auth::user();

            if ($user instanceof User) {
                ShopMember::withoutGlobalScopes()->create([
                    'shop_id' => $shop->getKey(),
                    'user_id' => $user->getKey(),
                    'role' => ShopMemberRole::Owner,
                    'is_active' => true,
                    'joined_at' => now(),
                ]);
            }

            return $shop;
        });
    }
}
