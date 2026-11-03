<?php

namespace App\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

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
     * A staff email is the sign-in identifier, unique among staff (ADR-0011).
     *
     * @return array<int, ValidationRule|Closure|array<mixed>|string>
     */
    protected function staffEmailRules(?User $except = null): array
    {
        return ['required', 'string', 'email:rfc', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($except): void {
            if (User::query()->staff()->whereRaw('lower(email) = ?', [Str::lower((string) $value)])->when($except, fn ($query) => $query->whereKeyNot($except->id))->exists()) {
                $fail(__('validation.unique', ['attribute' => $attribute]));
            }
        }];
    }

    /**
     * Several students may share a mobile number and one student may register
     * again with it (ADR-0010). Normalize before validating.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/'];
    }
}
