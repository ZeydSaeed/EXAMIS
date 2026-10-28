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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

/**
 * Student sign-in (ADR-0009). One identifier (username, mobile number,
 * student number or the QR link) selects the account. Knowing an
 * identifier is never enough on its own: the browser must be a remembered
 * device of that account, or the student must enter their private access
 * code. Unknown identifiers go through the same code step so the form does
 * not reveal which accounts exist.
 */
class StudentLoginController extends Controller
{
    private const PENDING = 'student_login.id';

    public function __construct(
        private StudentIdentity $identity,
        private TrustedDevices $devices,
        private AuditLogger $audit,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['identifier' => ['required', 'string', 'max:64']]);

        return $this->begin($request, $this->identity->findByIdentifier((string) $request->input('identifier')));
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

        return $this->begin($request, $this->identity->findByPublicId($publicId));
    }

    public function code(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has(self::PENDING)) {
            return to_route('login');
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
            return to_route('login');
        }

        $pendingId = (int) $request->session()->get(self::PENDING);
        $user = $pendingId > 0 ? User::query()->find($pendingId) : null;
        $lockKey = 'student-code|'.$pendingId;

        if (RateLimiter::tooManyAttempts($lockKey, (int) config('examis.students.access_code_attempts_per_hour'))) {
            $this->audit->record('auth.lockout', $user, ['via' => 'access_code']);

            throw ValidationException::withMessages([
                'access_code' => __('auth.access_code_locked'),
            ]);
        }

        if ($user === null || ! $user->isActive() || ! $user->isApplicant()
            || ! $this->identity->checkAccessCode($user, (string) $request->input('access_code'))) {
            RateLimiter::hit($lockKey, 3600);
            $this->audit->record('auth.failed', $user, ['via' => 'access_code']);

            throw ValidationException::withMessages([
                'access_code' => __('auth.access_code_failed'),
            ]);
        }

        RateLimiter::clear($lockKey);

        if ($request->boolean('trust_device')) {
            $this->devices->trust($request, $user);
            $this->audit->record('student.device_trusted', $user, actor: $user);
        }

        return $this->signIn($request, $user);
    }

    private function begin(Request $request, ?User $user): RedirectResponse
    {
        if ($user !== null && $user->isActive() && $this->devices->recognizes($request, $user)) {
            return $this->signIn($request, $user);
        }

        $request->session()->put(self::PENDING, $user !== null && $user->isActive() ? $user->id : 0);

        return to_route('student.code');
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget(self::PENDING);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(Fortify::redirects('login'));
    }
}
