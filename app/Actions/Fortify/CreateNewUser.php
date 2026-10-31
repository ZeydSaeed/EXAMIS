<?php

namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Public\ApplicationTermsController;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Students\StudentIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use ProfileValidationRules;

    public function __construct(
        private StudentIdentity $identity,
        private AuditLogger $audit,
        private Request $request,
    ) {}

    /**
     * Create a one-time student account from a mobile number only (ADR-0010):
     * every registration is a new account with its own student number, even
     * for a number used before. The number is not verified: no SMS or
     * WhatsApp message is sent. The application terms must have been accepted first (the
     * terms step). The access code waits in the session until the student
     * card shows it once, and is stored in plain text. Staff accounts are created by administrators
     * only.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $termsAcceptedAt = ApplicationTermsController::acceptedAt($this->request);

        if ($termsAcceptedAt === null) {
            throw ValidationException::withMessages([
                'terms' => __('auth.application_terms_required'),
            ]);
        }

        $input['phone'] = StudentIdentity::normalizePhone($input['phone'] ?? null);

        Validator::make($input, [
            'phone' => $this->phoneRules(),
            'terms' => ['accepted'],
        ])->validate();

        [$user, $code] = DB::transaction(function () use ($input, $termsAcceptedAt): array {
            $user = User::create([
                'name' => $input['phone'],
                'phone' => $input['phone'],
                'locale' => app()->getLocale(),
            ]);

            $this->identity->ensureIdentity($user);
            $user->forceFill(['name' => (string) ($user->student_number ?? $input['phone'])])->save();
            $code = $this->identity->issueAccessCode($user);

            $this->audit->record('student.registered', $user, [
                'student_number' => $user->student_number,
                'application_terms_accepted_at' => $termsAcceptedAt->toIso8601String(),
            ], actor: $user);

            return [$user, $code];
        });

        $this->request->session()->forget(ApplicationTermsController::SESSION_KEY);
        $this->request->session()->put('student.access_code', $code);

        return $user;
    }
}
