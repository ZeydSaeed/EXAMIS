<?php

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the applicant their application changed status. Contains only the
 * reference number, status and reviewer reason: no personal data.
 * Delivered in the portal only: email addresses are not verified
 * (ADR-0009), so nothing is mailed to an address that may not be theirs.
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
        return ['database'];
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

    /**
     * Every try failed: the applicant still sees the status on their
     * application page, but the missing notice must reach the logs.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Application status notification failed after every retry.', [
            'application' => $this->application->uuid,
            'status' => $this->status->value,
            'error' => $exception?->getMessage(),
        ]);
    }
}
