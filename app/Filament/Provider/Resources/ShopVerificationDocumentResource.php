<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\ShopVerificationDocumentResource\Pages;
use App\Models\Shop;
use App\Models\ShopVerificationDocument;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class ShopVerificationDocumentResource extends Resource
{
    protected static ?string $model = ShopVerificationDocument::class;

    protected static ?string $tenantOwnershipRelationshipName = 'shop';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Compliance & Trust';

    protected static ?string $navigationLabel = 'Verification Documents';

    protected static ?string $modelLabel = 'Compliance Document';

    protected static ?int $navigationSort = 40;

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
        return false;
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
        return $form
            ->schema([
                Forms\Components\Section::make('Compliance & Certification Document Upload')
                    ->description('Upload private business credentials for RepairPal certification review.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('document_type')
                            ->label('Document Type')
                            ->options([
                                'business_license' => 'State / Municipal Business License',
                                'garage_liability_insurance' => 'Garage Keepers & Liability Insurance',
                                'ase_certification' => 'ASE Master Technician Certification',
                                'epa_permit' => 'EPA / BAR Automotive Repair Permit',
                            ])
                            ->native(false)
                            ->required(),

                        Forms\Components\DatePicker::make('expires_at')
                            ->label('Expiration Date')
                            ->native(false),

                        Forms\Components\FileUpload::make('file_path')
                            ->label('Credential Document (PDF / Image)')
                            ->disk('local')
                            ->directory('private/shop-verification-documents')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(10240)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Credential Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'business_license' => 'Business License',
                        'garage_liability_insurance' => 'Garage Liability Insurance',
                        'ase_certification' => 'ASE Certification',
                        'epa_permit' => 'EPA / BAR Permit',
                        default => $state,
                    })
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Review Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date('M d, Y')
                    ->placeholder('No Expiry'),

                Tables\Columns\TextColumn::make('rejection_reason')
                    ->label('Admin Review Notes')
                    ->placeholder('—')
                    ->limit(50),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M d, Y'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopVerificationDocuments::route('/'),
            'create' => Pages\CreateShopVerificationDocument::route('/create'),
        ];
    }
}
