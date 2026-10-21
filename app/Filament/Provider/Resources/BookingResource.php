<?php

declare(strict_types=1);

namespace App\Filament\Provider\Resources;

use App\Enums\BookingStatus;
use App\Filament\Provider\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Shop;
use App\Models\User;
use App\Services\BookingStateMachineService;
use Carbon\Carbon;
use DomainException;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $tenantOwnershipRelationshipName = 'shop';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Customer Inquiries & Bookings';

    protected static ?string $navigationLabel = 'Appointments & Queue';

    protected static ?string $modelLabel = 'Appointment';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
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

    private static function currentUserIsActiveShopMember(): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        return $user instanceof User && $tenant instanceof Shop && $user->canAccessTenant($tenant);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer Appointment Details')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->disabled(),

                        Forms\Components\TextInput::make('customer_email')
                            ->label('Customer Email')
                            ->disabled(),

                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Customer Phone')
                            ->disabled(),

                        Forms\Components\DatePicker::make('preferred_date')
                            ->label('Requested Date')
                            ->disabled(),

                        Forms\Components\TextInput::make('preferred_time_slot')
                            ->label('Preferred Window')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Current Status')
                            ->options(BookingStatus::class)
                            ->disabled(),

                        Forms\Components\Textarea::make('issue_description')
                            ->label('Customer Issue Notes')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('shop_notes')
                            ->label('Internal / Customer Shop Notes')
                            ->rows(3)
                            ->columnSpanFull(),
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
                    ->description(fn (Booking $record): string => $record->customer_phone),

                Tables\Columns\TextColumn::make('vehicle_summary')
                    ->label('Vehicle')
                    ->state(fn (Booking $record): string => $record->vehicle
                        ? "{$record->vehicle->year} {$record->vehicle->make} {$record->vehicle->model}"
                        : '—'),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->searchable(),

                Tables\Columns\TextColumn::make('preferred_date')
                    ->label('Requested Date')
                    ->date('M d, Y')
                    ->description(fn (Booking $record): string => ucfirst($record->preferred_time_slot))
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Confirmed Bay Time')
                    ->dateTime('M d, Y g:i A')
                    ->placeholder('Pending Confirmation'),
            ])
            ->defaultSort('preferred_date', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(BookingStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm_booking')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => $record->status->canTransitionTo(BookingStatus::Confirmed) && self::currentUserCanManageShop())
                    ->form([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Confirmed Appointment Date & Time')
                            ->default(fn (Booking $record): string => $record->preferred_date->format('Y-m-d') . ' 09:00:00')
                            ->native(false)
                            ->required(),
                        Forms\Components\Textarea::make('note')
                            ->label('Confirmation Instructions for Customer')
                            ->placeholder('Please drop off keys at the front service desk 10 minutes prior.'),
                    ])
                    ->action(function (Booking $record, array $data, BookingStateMachineService $stateMachine): void {
                        try {
                            $stateMachine->transition(
                                booking: $record,
                                targetStatus: BookingStatus::Confirmed,
                                actorUserId: (string) Auth::id(),
                                note: isset($data['note']) ? (string) $data['note'] : null,
                                scheduledAt: Carbon::parse((string) $data['scheduled_at']),
                            );

                            Notification::make()
                                ->title('Appointment Confirmed')
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title('Cannot Confirm Appointment')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('propose_alternate')
                    ->label('Propose Time')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->visible(fn (Booking $record): bool => $record->status->canTransitionTo(BookingStatus::Rescheduled) && self::currentUserCanManageShop())
                    ->form([
                        Forms\Components\DateTimePicker::make('alternate_proposed_at')
                            ->label('Proposed Alternate Date & Time')
                            ->native(false)
                            ->required(),
                        Forms\Components\Textarea::make('note')
                            ->label('Reason / Note to Customer')
                            ->required(),
                    ])
                    ->action(function (Booking $record, array $data, BookingStateMachineService $stateMachine): void {
                        try {
                            $stateMachine->transition(
                                booking: $record,
                                targetStatus: BookingStatus::Rescheduled,
                                actorUserId: (string) Auth::id(),
                                note: (string) $data['note'],
                                alternateProposedAt: Carbon::parse((string) $data['alternate_proposed_at']),
                            );

                            Notification::make()
                                ->title('Alternate Schedule Proposed')
                                ->info()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title('Action Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('start_service')
                    ->label('Start Work')
                    ->icon('heroicon-o-wrench')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record): bool => $record->status->canTransitionTo(BookingStatus::InProgress) && self::currentUserIsActiveShopMember())
                    ->action(function (Booking $record, BookingStateMachineService $stateMachine): void {
                        try {
                            $stateMachine->transition(
                                booking: $record,
                                targetStatus: BookingStatus::InProgress,
                                actorUserId: (string) Auth::id(),
                                note: 'Vehicle checked into service bay.',
                            );

                            Notification::make()
                                ->title('Repair Marked In Progress')
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title('Cannot Start Service')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('complete_service')
                    ->label('Complete')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record): bool => $record->status->canTransitionTo(BookingStatus::Completed) && self::currentUserIsActiveShopMember())
                    ->action(function (Booking $record, BookingStateMachineService $stateMachine): void {
                        try {
                            $stateMachine->transition(
                                booking: $record,
                                targetStatus: BookingStatus::Completed,
                                actorUserId: (string) Auth::id(),
                                note: 'Repair service completed and quality checked.',
                            );

                            Notification::make()
                                ->title('Appointment Completed')
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title('Cannot Complete Service')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('decline_booking')
                    ->label('Decline')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Booking $record): bool => $record->status->canTransitionTo(BookingStatus::Declined) && self::currentUserCanManageShop())
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Decline Reason for Customer')
                            ->required(),
                    ])
                    ->action(function (Booking $record, array $data, BookingStateMachineService $stateMachine): void {
                        try {
                            $stateMachine->transition(
                                booking: $record,
                                targetStatus: BookingStatus::Declined,
                                actorUserId: (string) Auth::id(),
                                note: (string) $data['note'],
                            );

                            Notification::make()
                                ->title('Appointment Request Declined')
                                ->warning()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title('Cannot Decline Request')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
