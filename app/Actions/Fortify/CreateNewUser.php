<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered applicant account. Staff
     * accounts are created by administrators only.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['username'] = Str::lower(trim((string) ($input['username'] ?? '')));

        Validator::make($input, [
            'username' => $this->usernameRules(),
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ])->validate();

        return User::create([
            'username' => $input['username'],
            'name' => $input['name'],
            'email' => Str::lower($input['email']),
            'password' => $input['password'],
            'locale' => app()->getLocale(),
        ]);
    }
}
