<?php

namespace App\Http\Requests\Applications;

use App\Enums\Gender;
use App\Enums\ReferenceType;
use App\Models\Application;
use App\Services\ReferenceData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Draft saves accept partial data (every field nullable) but each value that
 * is present must be well formed; completeness is enforced at submission.
 * The branch and specialization are not part of the form: they are chosen
 * on their own page (SpecializationController) and shown read-only here.
 */
class SaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->application());

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $namePart = ['nullable', 'string', 'min:2', 'max:40', 'regex:/^[\p{Arabic}\s\.\-]+$/u'];
        $houseNumber = ['nullable', 'string', 'max:10', 'regex:/^[0-9A-Za-z\/\-]+$/'];

        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'student_name' => $namePart,
            'father_name' => $namePart,
            'grandfather_name' => $namePart,
            'great_grandfather_name' => $namePart,
            'mother_first_name' => $namePart,
            'mother_father_name' => $namePart,
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:-10 years', 'after:1940-01-01'],
            'birth_place_code' => ['nullable', 'string', Rule::in([
                ...app(ReferenceData::class)->codes(ReferenceType::Governorate),
                Application::BIRTH_PLACE_OTHER,
            ])],
            'birth_place_other' => ['nullable', 'string', 'min:2', 'max:100'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'national_id_number' => ['nullable', 'string', 'regex:/^[0-9A-Za-z]{6,20}$/'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'district' => ['nullable', 'string', 'min:2', 'max:100'],
            'mahalla' => $houseNumber,
            'alley' => $houseNumber,
            'house_number' => $houseNumber,
            'school_name' => ['nullable', 'string', 'min:3', 'max:150'],
            'school_governorate_code' => ['nullable', 'string', Rule::in(app(ReferenceData::class)->codes(ReferenceType::Governorate))],
            'last_grade' => ['nullable', 'string', 'max:60'],
            'other_info' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Normalise Arabic-Indic digits and whitespace before validation so
     * applicants typing on Arabic keyboards are not rejected.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['national_id_number', 'mahalla', 'alley', 'house_number'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = preg_replace('/\s+/', '', strtr($this->input($field), [
                    '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                    '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                ]));
            }
        }

        foreach ([...Application::NAME_PARTS, ...Application::MOTHER_NAME_PARTS, 'birth_place_other', 'district', 'school_name', 'last_grade', 'other_info'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim((string) preg_replace('/\s+/u', ' ', $this->input($field)));
            }
        }

        // A written place of birth only goes with "other".
        if ($this->has('birth_place_code') && $this->input('birth_place_code') !== Application::BIRTH_PLACE_OTHER) {
            $normalized['birth_place_other'] = null;
        }

        // The mobile number is the account's (only the owner edits a draft).
        $normalized['phone'] = $this->user()?->phone;

        $this->merge($normalized);
    }

    public function application(): Application
    {
        /** @var Application */
        return $this->route('application');
    }
}
