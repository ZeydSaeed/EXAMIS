<?php

namespace App\Http\Requests\Applications;

use App\Enums\Gender;
use App\Enums\ReferenceType;
use App\Models\Application;
use App\Services\ReferenceData;
use Closure;
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
    /**
     * Rows of "documents issued to the student for study purposes" on the
     * official form.
     */
    public const MAX_ISSUED_DOCUMENTS = 4;

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
        $houseNumber = ['nullable', 'string', 'max:10', 'regex:/^[0-9A-Za-z\/\-]+$/'];

        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'full_name_ar' => $arabicName,
            'surname' => ['nullable', 'string', 'min:2', 'max:60', 'regex:/^[\p{Arabic}\s\.\-]+$/u'],
            'mother_name_ar' => $arabicName,
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:-10 years', 'after:1940-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'national_id_number' => ['nullable', 'string', 'regex:/^[0-9A-Za-z]{6,20}$/'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'district' => ['nullable', 'string', 'min:2', 'max:100'],
            'mahalla' => $houseNumber,
            'alley' => $houseNumber,
            'house_number' => $houseNumber,
            'school_name' => ['nullable', 'string', 'min:3', 'max:150'],
            'intermediate_school_name' => ['nullable', 'string', 'min:3', 'max:150'],
            'last_grade' => ['nullable', 'string', 'min:2', 'max:60'],
            'failed_years' => ['nullable', 'integer', 'min:0', 'max:20'],
            'left_years' => ['nullable', 'integer', 'min:0', 'max:20'],
            'branch_code' => ['nullable', 'string', Rule::in($branches)],
            'category_code' => ['nullable', 'string', Rule::in($categories), $this->specializationOfBranch($referenceData)],
            'other_info' => ['nullable', 'string', 'max:1000'],
            'issued_documents' => ['nullable', 'array', 'max:'.self::MAX_ISSUED_DOCUMENTS],
            'issued_documents.*' => ['array:number,date,recipient'],
            'issued_documents.*.number' => ['nullable', 'string', 'max:40'],
            'issued_documents.*.date' => ['nullable', 'date_format:Y-m-d', 'after:1940-01-01', 'before_or_equal:today'],
            'issued_documents.*.recipient' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * The specialization must belong to the chosen branch.
     */
    private function specializationOfBranch(ReferenceData $referenceData): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($referenceData): void {
            $branch = $this->input('branch_code');

            foreach ($referenceData->items(ReferenceType::Category) as $item) {
                if ($item['code'] === $value && $branch !== null && $item['parent_code'] !== $branch) {
                    $fail(__('applications.errors.specialization_branch'));
                }
            }
        };
    }

    /**
     * Normalise Arabic-Indic digits and whitespace before validation so
     * applicants typing on Arabic keyboards are not rejected.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['phone', 'national_id_number', 'mahalla', 'alley', 'house_number', 'failed_years', 'left_years'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = preg_replace('/\s+/', '', strtr($this->input($field), [
                    '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                    '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                ]));
            }
        }

        foreach (['full_name_ar', 'surname', 'mother_name_ar', 'district', 'school_name', 'intermediate_school_name', 'last_grade', 'other_info'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim((string) preg_replace('/\s+/u', ' ', $this->input($field)));
            }
        }

        if (is_array($this->input('issued_documents'))) {
            $normalized['issued_documents'] = array_values(array_filter(
                $this->input('issued_documents'),
                fn (mixed $row): bool => ! is_array($row) || array_filter($row, fn (mixed $value): bool => filled($value)) !== [],
            ));
        }

        $this->merge($normalized);
    }

    public function application(): Application
    {
        /** @var Application */
        return $this->route('application');
    }
}
