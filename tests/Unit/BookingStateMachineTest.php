<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BookingStatus;
use PHPUnit\Framework\TestCase;

final class BookingStateMachineTest extends TestCase
{
    public function test_requested_booking_allows_valid_transitions(): void
    {
        $requested = BookingStatus::Requested;

        self::assertTrue($requested->canTransitionTo(BookingStatus::Confirmed));
        self::assertTrue($requested->canTransitionTo(BookingStatus::Rescheduled));
        self::assertTrue($requested->canTransitionTo(BookingStatus::Declined));
        self::assertTrue($requested->canTransitionTo(BookingStatus::Cancelled));

        self::assertFalse($requested->canTransitionTo(BookingStatus::InProgress));
        self::assertFalse($requested->canTransitionTo(BookingStatus::Completed));
        self::assertFalse($requested->canTransitionTo(BookingStatus::Requested));
    }

    public function test_confirmed_and_in_progress_follow_strict_lifecycle(): void
    {
        self::assertTrue(BookingStatus::Confirmed->canTransitionTo(BookingStatus::InProgress));
        self::assertTrue(BookingStatus::Confirmed->canTransitionTo(BookingStatus::Cancelled));
        self::assertFalse(BookingStatus::Confirmed->canTransitionTo(BookingStatus::Completed));

        self::assertTrue(BookingStatus::InProgress->canTransitionTo(BookingStatus::Completed));
        self::assertFalse(BookingStatus::InProgress->canTransitionTo(BookingStatus::Cancelled));
        self::assertFalse(BookingStatus::InProgress->canCustomerCancel ?? BookingStatus::InProgress->isCustomerCancellable());
    }

    public function test_terminal_booking_states_prohibit_further_transitions(): void
    {
        foreach ([BookingStatus::Completed, BookingStatus::Declined, BookingStatus::Cancelled] as $terminal) {
            foreach (BookingStatus::cases() as $target) {
                self::assertFalse($terminal->canTransitionTo($target));
            }
            self::assertFalse($terminal->isCustomerCancellable());
        }
    }
}
