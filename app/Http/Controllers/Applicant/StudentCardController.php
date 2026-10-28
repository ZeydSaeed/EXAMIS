<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Students\StudentIdentity;
use App\Services\Students\TrustedDevices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\QrCode\Output\Svg;
use Mpdf\QrCode\QrCode;

/**
 * The student's permanent sign-in card: student number, QR code and the
 * one-time display of a newly issued access code (ADR-0009).
 */
class StudentCardController extends Controller
{
    public function __construct(
        private StudentIdentity $identity,
        private TrustedDevices $devices,
        private AuditLogger $audit,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isApplicant()) {
            return to_route('dashboard');
        }

        $this->identity->ensureIdentity($user);

        $qrUrl = route('student.qr', $user->public_id);

        return Inertia::render('applicant/student-card', [
            'student' => [
                'username' => $user->username,
                'student_number' => $user->student_number,
                'phone' => $user->phone,
                'has_access_code' => $user->access_code !== null,
                'access_code_issued_at' => $user->access_code_issued_at?->toIso8601String(),
                'trusted_devices' => $user->trustedDevices()->usable()->count(),
            ],
            'qr' => [
                'url' => $qrUrl,
                'svg' => (new Svg)->output(new QrCode($qrUrl, QrCode::ERROR_CORRECTION_MEDIUM), 220),
            ],
            'accessCode' => $request->session()->get('student.access_code'),
        ]);
    }

    /**
     * Replace the access code and sign out every other remembered device,
     * e.g. after the old code was shared or the card was lost.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isApplicant(), 403);

        $code = $this->identity->issueAccessCode($user);
        $revoked = $this->devices->revokeAll($user, except: $this->devices->current($request));

        $this->audit->record('student.access_code_regenerated', $user, ['revoked_devices' => $revoked]);

        return to_route('student.card')->with('student.access_code', $code);
    }
}
