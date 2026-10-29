<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'min:3', 'max:150'];
    }

    /**
     * Email is a contact channel that several accounts (e.g. siblings) may
     * share, so it is deliberately not unique (ADR-0002).
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return ['required', 'string', 'email:rfc', 'max:255'];
    }

    /**
     * The unique login identifier.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function usernameRules(): array
    {
        return ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9._\x{0621}-\x{063A}\x{0641}-\x{064A}-]+$/u', 'not_regex:/^[0-9]+$/', Rule::unique(User::class, 'username')];
    }

    /**
     * A student's mobile number is a sign-in identifier, so one number may
     * belong to one account only (ADR-0009). Normalize before validating.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(?int $userId = null, bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/', Rule::unique(User::class, 'phone')->ignore($userId)];
    }
}
