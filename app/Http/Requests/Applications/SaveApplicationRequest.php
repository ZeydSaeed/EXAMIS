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

        return $this->requireStep([
            'lock_version' => ['required', 'integer', 'min:1'],
            'step' => ['nullable', 'integer', Rule::in(array_keys(Application::STEP_FIELDS))],
            'student_name' => $namePart,
            'father_name' => $namePart,
            'grandfather_name' => $namePart,
            'great_grandfather_name' => $namePart,
            'mother_first_name' => $namePart,
            'mother_father_name' => $namePart,
            'mother_grandfather_name' => $namePart,
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
            'school_governorate_code' => ['nullable', 'string', Rule::in([
                ...app(ReferenceData::class)->codes(ReferenceType::Governorate),
                Application::SCHOOL_GOVERNORATE_OTHER,
            ])],
            'school_governorate_other' => ['nullable', 'string', 'min:2', 'max:100'],
            'last_grade' => ['nullable', 'string', 'max:60'],
            'other_info' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * "Save and continue" sends its step: the required fields of that step
     * must then be filled, because the form has no way back to them.
     *
     * @param  array<string, array<mixed>>  $rules
     * @return array<string, array<mixed>>
     */
    private function requireStep(array $rules): array
    {
        $required = array_intersect(
            Application::STEP_FIELDS[$this->integer('step')] ?? [],
            Application::REQUIRED_FOR_SUBMISSION,
            array_keys($rules),
        );

        foreach ($required as $field) {
            $rules[$field] = ['required', ...array_diff($rules[$field], ['nullable'])];
        }

        if ($this->integer('step') === 1 && $this->input('birth_place_code') === Application::BIRTH_PLACE_OTHER) {
            $rules['birth_place_other'] = ['required', ...array_diff($rules['birth_place_other'], ['nullable'])];
        }

        if ($this->integer('step') === 3 && $this->input('school_governorate_code') === Application::SCHOOL_GOVERNORATE_OTHER) {
            $rules['school_governorate_other'] = ['required', ...array_diff($rules['school_governorate_other'], ['nullable'])];
        }

        return $rules;
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

        foreach ([...Application::NAME_PARTS, ...Application::MOTHER_NAME_PARTS, 'birth_place_other', 'school_governorate_other', 'district', 'school_name', 'last_grade', 'other_info'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim((string) preg_replace('/\s+/u', ' ', $this->input($field)));
            }
        }

        // A written place of birth only goes with "other".
        if ($this->has('birth_place_code') && $this->input('birth_place_code') !== Application::BIRTH_PLACE_OTHER) {
            $normalized['birth_place_other'] = null;
        }

        // A written governorate only goes with "other".
        if ($this->has('school_governorate_code') && $this->input('school_governorate_code') !== Application::SCHOOL_GOVERNORATE_OTHER) {
            $normalized['school_governorate_other'] = null;
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
