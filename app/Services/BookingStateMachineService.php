<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatusEvent;
use App\Models\Shop;
use App\Models\ShopAvailabilitySlot;
use App\Notifications\BookingStatusChangedNotification;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class BookingStateMachineService
{
    /**
     * Create an idempotent customer appointment request (`AUTO-06-T01 & T03`).
     *
     * If a booking with the same `idempotency_key` already exists, returns the existing
     * booking without creating duplicates.
     *
     * @param  array{
     *     shop_id: string,
     *     customer_id?: string|null,
     *     vehicle_id: string,
     *     service_id: string,
     *     shop_quote_id?: string|null,
     *     idempotency_key: string,
     *     preferred_date: string,
     *     preferred_time_slot: string,
     *     customer_name: string,
     *     customer_email: string,
     *     customer_phone: string,
     *     issue_description?: string|null
     * }  $payload
     */
    public function createBookingRequest(array $payload, ?string $actorUserId = null): Booking
    {
        return DB::transaction(function () use ($payload, $actorUserId): Booking {
            $normalizedEmail = strtolower(trim($payload['customer_email']));

            $existing = Booking::withoutGlobalScopes()
                ->where('shop_id', $payload['shop_id'])
                ->whereRaw('LOWER(customer_email) = ?', [$normalizedEmail])
                ->where('idempotency_key', $payload['idempotency_key'])
                ->first();

            if ($existing instanceof Booking) {
                return $existing;
            }

            /** @var Shop $shop */
            $shop = Shop::query()
                ->whereKey($payload['shop_id'])
                ->where('is_active', true)
                ->where('is_certified', true)
                ->firstOrFail();

            /** @var Booking $booking */
            $booking = Booking::withoutGlobalScopes()->create([
                'shop_id' => $shop->id,
                'customer_id' => $payload['customer_id'] ?? null,
                'vehicle_id' => $payload['vehicle_id'],
                'service_id' => $payload['service_id'],
                'shop_quote_id' => $payload['shop_quote_id'] ?? null,
                'idempotency_key' => $payload['idempotency_key'],
                'status' => BookingStatus::Requested,
                'preferred_date' => $payload['preferred_date'],
                'preferred_time_slot' => $payload['preferred_time_slot'],
                'customer_name' => $payload['customer_name'],
                'customer_email' => $payload['customer_email'],
                'customer_phone' => $payload['customer_phone'],
                'issue_description' => $payload['issue_description'] ?? null,
            ]);

            BookingStatusEvent::withoutGlobalScopes()->create([
                'shop_id' => $shop->id,
                'booking_id' => $booking->id,
                'actor_user_id' => $actorUserId ?? ($payload['customer_id'] ?? null),
                'from_status' => null,
                'to_status' => BookingStatus::Requested->value,
                'note' => 'Customer submitted appointment request.',
                'created_at' => now(),
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $actorUserId ?? ($payload['customer_id'] ?? null),
                'shop_id' => $shop->id,
                'action' => 'booking.requested',
                'auditable_type' => Booking::class,
                'auditable_id' => $booking->id,
                'metadata' => [
                    'preferred_date' => $payload['preferred_date'],
                    'preferred_time_slot' => $payload['preferred_time_slot'],
                ],
            ]);

            return $booking;
        });
    }

    /**
     * Atomically transition a booking to a new state with row-level locking (`lockForUpdate`),
     * shop bay capacity conflict verification, audit logging, and customer email notification.
     */
    public function transition(
        Booking $booking,
        BookingStatus $targetStatus,
        ?string $actorUserId = null,
        ?string $note = null,
        ?CarbonInterface $scheduledAt = null,
        ?CarbonInterface $alternateProposedAt = null,
    ): Booking {
        $updatedBooking = DB::transaction(function () use (
            $booking,
            $targetStatus,
            $actorUserId,
            $note,
            $scheduledAt,
            $alternateProposedAt,
        ): Booking {
            /** @var Booking $locked */
            $locked = Booking::withoutGlobalScopes()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $locked->status instanceof BookingStatus
                ? $locked->status
                : BookingStatus::from((string) $locked->status);

            if (! $currentStatus->canTransitionTo($targetStatus)) {
                throw new DomainException(
                    "Invalid booking state transition from [{$currentStatus->value}] to [{$targetStatus->value}].",
                );
            }

            if ($targetStatus === BookingStatus::Confirmed) {
                $this->assertBayAvailabilityForConfirmation($locked);
            }

            $updates = [
                'status' => $targetStatus,
            ];

            if ($targetStatus === BookingStatus::Confirmed) {
                $updates['scheduled_at'] = $scheduledAt ?? $locked->scheduled_at ?? now()->addDay();
            }

            if ($targetStatus === BookingStatus::Rescheduled && $alternateProposedAt !== null) {
                $updates['alternate_proposed_at'] = $alternateProposedAt;
            }

            if ($targetStatus === BookingStatus::Cancelled && $note !== null) {
                $updates['cancellation_reason'] = $note;
            }

            if ($note !== null && in_array($targetStatus, [BookingStatus::Confirmed, BookingStatus::Rescheduled, BookingStatus::Declined], true)) {
                $updates['shop_notes'] = $note;
            }

            $locked->update($updates);

            BookingStatusEvent::withoutGlobalScopes()->create([
                'shop_id' => $locked->shop_id,
                'booking_id' => $locked->id,
                'actor_user_id' => $actorUserId,
                'from_status' => $currentStatus->value,
                'to_status' => $targetStatus->value,
                'note' => $note,
                'created_at' => now(),
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $actorUserId,
                'shop_id' => $locked->shop_id,
                'action' => "booking.{$targetStatus->value}",
                'auditable_type' => Booking::class,
                'auditable_id' => $locked->id,
                'metadata' => [
                    'from_status' => $currentStatus->value,
                    'to_status' => $targetStatus->value,
                    'note' => $note,
                ],
            ]);

            return $locked->fresh() ?? $locked;
        });

        try {
            Notification::route('mail', $updatedBooking->customer_email)
                ->notify(new BookingStatusChangedNotification(
                    booking: $updatedBooking,
                    previousStatus: $booking->status->value,
                    newStatus: $targetStatus->value,
                    note: $note,
                ));
        } catch (Throwable $exception) {
            Log::warning('Booking email notification delivery failed', [
                'booking_id' => $updatedBooking->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $updatedBooking;
    }

    /**
     * Verify the shop is open on the requested day and has not exceeded its `max_concurrent_bookings`
     * for the same date and time slot (`AUTO-06-T03`).
     */
    private function assertBayAvailabilityForConfirmation(Booking $booking): void
    {
        $dayOfWeek = (int) $booking->preferred_date->dayOfWeek;

        $slot = ShopAvailabilitySlot::withoutGlobalScopes()
            ->where('shop_id', $booking->shop_id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if ($slot !== null && $slot->is_closed) {
            throw new DomainException('Cannot confirm appointment on a day when the shop is marked closed.');
        }

        $maxConcurrent = $slot !== null ? (int) $slot->max_concurrent_bookings : 3;

        $confirmedCount = Booking::withoutGlobalScopes()
            ->where('shop_id', $booking->shop_id)
            ->whereDate('preferred_date', $booking->preferred_date->toDateString())
            ->where('preferred_time_slot', $booking->preferred_time_slot)
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::InProgress->value])
            ->whereKeyNot($booking->getKey())
            ->count();

        if ($confirmedCount >= $maxConcurrent) {
            throw new DomainException(
                "Bay capacity reached ({$maxConcurrent} concurrent bookings) for {$booking->preferred_date->toDateString()} ({$booking->preferred_time_slot}). Please propose an alternate schedule.",
            );
        }
    }
}
