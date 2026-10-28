<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Students\StudentIdentity;
use App\Services\Students\TrustedDevices;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Access recovery for a student who lost their access code and every
 * remembered device (ADR-0009). Staff confirm the student's identity in
 * person first; the new code is shown to the staff member once, every old
 * device is signed out and the reset is audited.
 */
class StudentAccessController extends Controller
{
    public function store(User $user, StudentIdentity $identity, TrustedDevices $devices, AuditLogger $audit): RedirectResponse
    {
        abort_unless($user->isApplicant(), 404);

        $identity->ensureIdentity($user);
        $code = $identity->issueAccessCode($user);
        $revoked = $devices->revokeAll($user);

        $audit->record('student.access_code_reset', $user, ['revoked_devices' => $revoked]);

        Inertia::flash('student_access_code', [
            'user_id' => $user->id,
            'code' => $code,
        ]);

        return back();
    }
}
