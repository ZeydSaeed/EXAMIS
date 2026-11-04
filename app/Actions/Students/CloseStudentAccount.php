<?php

namespace App\Actions\Students;

use App\Models\Application;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Students\TrustedDevices;
use Illuminate\Support\Facades\DB;

/**
 * Ends a one-time student account once the student confirms the form was
 * printed or downloaded (ADR-0010). Signing in stops at once: the access code
 * and every remembered device are removed. An application still waiting on
 * the student keeps the account open.
 */
class CloseStudentAccount
{
    public function __construct(
        private TrustedDevices $devices,
        private AuditLogger $audit,
    ) {}

    public function handle(User $student, Application $application): void
    {
        if ($student->isClosed() || $application->waitsOnStudent()) {
            return;
        }

        // One unit: a crash between the writes must not leave a closed
        // account whose remembered devices still sign in, or no audit row.
        DB::transaction(function () use ($student, $application): void {
            $locked = User::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            if ($locked->isClosed()) {
                return;
            }

            $locked->forceFill([
                'closed_at' => now(),
                'access_code' => null,
            ])->save();

            $this->devices->revokeAll($locked);

            $this->audit->record('student.account_closed', $locked, ['reference' => $application->reference_number], $locked);
        }, 3);

        $student->refresh();
    }
}
