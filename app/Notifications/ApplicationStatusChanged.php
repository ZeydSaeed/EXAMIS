<?php

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the applicant their application changed status. Contains only the
 * reference number, status and reviewer reason: no personal data.
 */
class ApplicationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public Application $application,
        public ApplicationStatus $status,
        public ?string $reason = null,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->hasVerifiedEmail() ? ['database', 'mail'] : ['database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('notifications.status_changed.subject', ['reference' => $this->application->reference_number]))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.status_changed.line', [
                'reference' => $this->application->reference_number,
                'status' => $this->status->label(),
            ]));

        if ($this->reason !== null) {
            $message->line(__('notifications.status_changed.reason', ['reason' => $this->reason]));
        }

        return $message->action(__('notifications.status_changed.action'), route('applications.show', $this->application));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'application' => $this->application->uuid,
            'reference' => $this->application->reference_number,
            'status' => $this->status->value,
            'reason' => $this->reason,
        ];
    }
}
