<?php

namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Students\StudentIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use ProfileValidationRules;

    public function __construct(private StudentIdentity $identity, private AuditLogger $audit) {}

    /**
     * Create a student account from a username and a mobile number only
     * (ADR-0009). The number is not verified: no SMS or WhatsApp message is
     * sent. The one-time access code is flashed for the welcome card and is
     * never stored in plain text. Staff accounts are created by
     * administrators only.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['username'] = Str::lower(trim((string) ($input['username'] ?? '')));
        $input['phone'] = StudentIdentity::normalizePhone($input['phone'] ?? null);

        Validator::make($input, [
            'username' => $this->usernameRules(),
            'phone' => $this->phoneRules(),
            'terms' => ['accepted'],
        ])->validate();

        [$user, $code] = DB::transaction(function () use ($input): array {
            $user = User::create([
                'username' => $input['username'],
                'name' => $input['username'],
                'phone' => $input['phone'],
                'locale' => app()->getLocale(),
            ]);

            $this->identity->ensureIdentity($user);
            $code = $this->identity->issueAccessCode($user);

            $this->audit->record('student.registered', $user, [
                'student_number' => $user->student_number,
            ], actor: $user);

            return [$user, $code];
        });

        session()->flash('student.access_code', $code);

        return $user;
    }
}
