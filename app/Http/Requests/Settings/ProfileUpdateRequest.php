<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Services\Students\StudentIdentity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (! $this->user()->isApplicant()) {
            return $this->profileRules($this->user()->id);
        }

        // Students may add an email but are not required to. Older accounts
        // made before ADR-0009 may add a number; once set it cannot be removed.
        return [
            'name' => $this->nameRules(),
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => $this->phoneRules($this->user()->id, required: $this->user()->phone !== null),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()->isApplicant()) {
            $this->merge(['phone' => StudentIdentity::normalizePhone($this->input('phone'))]);
        }
    }
}
