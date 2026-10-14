<?php

namespace App\Http\Requests\Admin;

use App\Enums\ExamPeriodStatus;
use App\Enums\Permission;
use App\Enums\ReferenceType;
use App\Models\ExamPeriod;
use App\Services\ReferenceData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::PeriodsManage) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $referenceData = app(ReferenceData::class);
        /** @var ExamPeriod|null $period */
        $period = $this->route('period');

        return [
            'code' => ['required', 'string', 'regex:/^[A-Z0-9]{2,12}$/', Rule::unique('exam_periods', 'code')->ignore($period?->id)],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'status' => ['required', Rule::enum(ExamPeriodStatus::class)],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'eligible_branch_codes' => ['nullable', 'array'],
            'eligible_branch_codes.*' => ['string', Rule::in($referenceData->codes(ReferenceType::Branch))],
            'eligible_category_codes' => ['nullable', 'array'],
            'eligible_category_codes.*' => ['string', Rule::in($referenceData->codes(ReferenceType::Category))],
            'instructions_ar' => ['nullable', 'string', 'max:5000'],
            'instructions_en' => ['nullable', 'string', 'max:5000'],
            'documents' => ['array'],
            'documents.*.document_type_id' => ['required', 'integer', 'exists:document_types,id', 'distinct'],
            'documents.*.is_required' => ['required', 'boolean'],
        ];
    }

    /**
     * An empty eligibility list means "everyone"; store it as null.
     *
     * @return array<string, mixed>
     */
    public function periodAttributes(): array
    {
        $attributes = $this->safe()->except('documents');

        foreach (['eligible_branch_codes', 'eligible_category_codes'] as $key) {
            $attributes[$key] = empty($attributes[$key]) ? null : array_values($attributes[$key]);
        }

        return $attributes;
    }

    /**
     * @return array<int, array{is_required: bool}>
     */
    public function documentRequirements(): array
    {
        /** @var list<array{document_type_id: int|string, is_required: bool|int|string}> $rows */
        $rows = $this->validated('documents', []);
        $requirements = [];

        foreach ($rows as $row) {
            $requirements[(int) $row['document_type_id']] = ['is_required' => (bool) $row['is_required']];
        }

        return $requirements;
    }
}
