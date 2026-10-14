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
        $referenceData = app(ReferenceData::class);
        $period = $this->application()->examPeriod;

        $branches = $referenceData->codes(ReferenceType::Branch);
        $categories = $referenceData->codes(ReferenceType::Category);

        if ($period->eligible_branch_codes !== null) {
            $branches = array_values(array_intersect($branches, $period->eligible_branch_codes));
        }

        if ($period->eligible_category_codes !== null) {
            $categories = array_values(array_intersect($categories, $period->eligible_category_codes));
        }

        $arabicName = ['nullable', 'string', 'min:3', 'max:150', 'regex:/^[\p{Arabic}\s\.\-]+$/u'];

        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'full_name_ar' => $arabicName,
            'mother_name_ar' => $arabicName,
            'full_name_en' => ['nullable', 'string', 'min:3', 'max:150', 'regex:/^[A-Za-z\s\.\'\-]+$/'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:-10 years', 'after:1940-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'national_id_number' => ['nullable', 'string', 'regex:/^[0-9A-Za-z]{6,20}$/'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'contact_email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'governorate_code' => ['nullable', 'string', Rule::in($referenceData->codes(ReferenceType::Governorate))],
            'directorate_code' => ['nullable', 'string', Rule::in($referenceData->codes(ReferenceType::Directorate))],
            'school_name' => ['nullable', 'string', 'min:3', 'max:150'],
            'branch_code' => ['nullable', 'string', Rule::in($branches)],
            'category_code' => ['nullable', 'string', Rule::in($categories)],
            'previous_exam_year' => ['nullable', 'integer', 'min:1990', 'max:'.now()->year],
        ];
    }

    /**
     * Normalise Arabic-Indic digits and whitespace before validation so
     * applicants typing on Arabic keyboards are not rejected.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['phone', 'national_id_number'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = preg_replace('/\s+/', '', strtr($this->input($field), [
                    '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                    '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                ]));
            }
        }

        foreach (['full_name_ar', 'mother_name_ar', 'full_name_en', 'school_name', 'address'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim((string) preg_replace('/\s+/u', ' ', $this->input($field)));
            }
        }

        $this->merge($normalized);
    }

    public function application(): Application
    {
        /** @var Application */
        return $this->route('application');
    }
}
