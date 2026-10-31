<?php

namespace App\Actions\Students;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Students\TrustedDevices;

/**
 * Ends a one-time student account when the student opens their form for
 * printing (ADR-0010). Signing in stops at once: the access code and every
 * remembered device are removed. An application still waiting on the student
 * keeps the account open.
 */
class CloseStudentAccount
{
    public function __construct(
        private TrustedDevices $devices,
        private AuditLogger $audit,
    ) {}

    public function handle(User $student, Application $application): void
    {
        if ($student->isClosed() || in_array($application->status, [ApplicationStatus::RequiresCorrection, ApplicationStatus::Withdrawn], true)) {
            return;
        }

        $student->forceFill([
            'closed_at' => now(),
            'access_code' => null,
        ])->save();

        $this->devices->revokeAll($student);

        $this->audit->record('student.account_closed', $student, ['reference' => $application->reference_number], $student);
    }
}
