<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BookingStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly string $previousStatus,
        private readonly string $newStatus,
        private readonly ?string $note = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = $this->booking->status->getLabel();

        $mail = (new MailMessage())
            ->subject("RepairPal Appointment Update: {$statusLabel}")
            ->greeting("Hello {$this->booking->customer_name},")
            ->line("Your appointment request has transitioned from [{$this->previousStatus}] to [{$statusLabel}].")
            ->line("Preferred Date: {$this->booking->preferred_date->format('M d, Y')} (" . ucfirst($this->booking->preferred_time_slot) . ')');

        if ($this->booking->scheduled_at !== null) {
            $mail->line("Confirmed Schedule: {$this->booking->scheduled_at->format('M d, Y g:i A')}");
        }

        if ($this->booking->alternate_proposed_at !== null) {
            $mail->line("Proposed Alternate Schedule: {$this->booking->alternate_proposed_at->format('M d, Y g:i A')}");
        }

        if ($this->note !== null && $this->note !== '') {
            $mail->line("Shop Note: {$this->note}");
        }

        return $mail->action('View Appointment Status', url('/account'));
    }
}
