<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Students\StudentIdentity;
use App\Services\Students\TrustedDevices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student sign-in (ADR-0009, ADR-0010). The student number or the QR link
 * selects the account. Knowing an
 * identifier is never enough on its own: the browser must be a remembered
 * device of that account, or the student must enter their private access
 * code. Unknown identifiers go through the same code step so the form does
 * not reveal which accounts exist. "Remember me" keeps the session signed
 * in after the browser closes.
 */
class StudentLoginController extends Controller
{
    private const PENDING = 'student_login.id';

    /**
     * Who the failed codes count against: the account, or for an unknown
     * identifier a digest of what was typed, so that every identifier,
     * known or not, has a counter of its own and locks the same way.
     */
    private const SUBJECT = 'student_login.subject';

    private const REMEMBER = 'student_login.remember';

    public function __construct(
        private StudentIdentity $identity,
        private TrustedDevices $devices,
        private AuditLogger $audit,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:64'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $request->session()->put(self::REMEMBER, $request->boolean('remember'));
        $identifier = (string) $request->input('identifier');

        return $this->begin($request, $this->identity->findByIdentifier($identifier), 'number:'.(StudentIdentity::normalizePhone($identifier) ?? Str::lower(trim($identifier))));
    }

    /**
     * Entry point printed in the student's QR code. The code carries only a
     * random id, never personal data, and leads to the same checks.
     */
    public function qr(Request $request, string $publicId): RedirectResponse
    {
        if ($request->user() !== null && $request->user()->public_id === strtolower($publicId)) {
            return to_route('dashboard');
        }

        if ($request->user() !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->session()->forget(self::REMEMBER);

        return $this->begin($request, $this->identity->findByPublicId($publicId), 'qr:'.Str::lower($publicId));
    }

    public function code(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has(self::PENDING)) {
            return to_route('apply.terms');
        }

        return Inertia::render('auth/student-code');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'access_code' => ['required', 'string', 'max:20'],
            'trust_device' => ['nullable', 'boolean'],
        ]);

        if (! $request->session()->has(self::PENDING)) {
            return to_route('apply.terms');
        }

        $pendingId = (int) $request->session()->get(self::PENDING);
        $user = $pendingId > 0 ? User::query()->find($pendingId) : null;

        // A few tries per device keep a guesser from locking the real
        // student out; the overall ceiling still stops guessing spread over
        // many devices.
        $subject = 'student-code|'.$request->session()->get(self::SUBJECT, 'none');
        $locks = [
            $subject.'|'.$request->ip() => (int) config('examis.students.access_code_attempts_per_hour'),
            $subject => (int) config('examis.students.access_code_attempts_per_hour_total'),
        ];

        foreach ($locks as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $this->audit->record('auth.lockout', $user, ['via' => 'access_code']);

                throw ValidationException::withMessages([
                    'access_code' => __('auth.access_code_locked'),
                ]);
            }
        }

        if ($user === null || ! $user->isActive() || ! $user->isApplicant()
            || ! $this->identity->checkAccessCode($user, (string) $request->input('access_code'))) {
            foreach (array_keys($locks) as $key) {
                RateLimiter::hit($key, 3600);
            }

            $this->audit->record('auth.failed', $user, ['via' => 'access_code']);

            throw ValidationException::withMessages([
                'access_code' => __('auth.access_code_failed'),
            ]);
        }

        foreach (array_keys($locks) as $key) {
            RateLimiter::clear($key);
        }

        if ($request->boolean('trust_device')) {
            $this->devices->trust($request, $user);
            $this->audit->record('student.device_trusted', $user, actor: $user);
        }

        return $this->signIn($request, $user);
    }

    /**
     * @param  string  $typed  what selected the account, normalized; only its digest is kept
     */
    private function begin(Request $request, ?User $user, string $typed): RedirectResponse
    {
        if ($user !== null && $user->isActive() && $this->devices->recognizes($request, $user)) {
            return $this->signIn($request, $user);
        }

        $known = $user !== null && $user->isActive();
        $request->session()->put(self::PENDING, $known ? $user->id : 0);
        $request->session()->put(self::SUBJECT, hash_hmac('sha256', $known ? 'account:'.$user->id : $typed, (string) config('app.key')));

        return to_route('student.code');
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        $remember = (bool) $request->session()->pull(self::REMEMBER, false);
        $request->session()->forget([self::PENDING, self::SUBJECT]);

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
