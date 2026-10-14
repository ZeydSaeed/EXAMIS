<?php

namespace App\Http\Resources;

use App\Enums\ReferenceType;
use App\Models\Application;
use App\Services\ReferenceData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $referenceData = app(ReferenceData::class);

        return [
            'uuid' => $this->uuid,
            'reference_number' => $this->reference_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'lock_version' => $this->lock_version,
            'is_editable' => $this->status->isEditableByApplicant(),
            'exam_period' => $this->whenLoaded('examPeriod', fn (): array => [
                'id' => $this->examPeriod->id,
                'code' => $this->examPeriod->code,
                'name' => $this->examPeriod->localizedName(),
                'closes_at' => $this->examPeriod->closes_at->toIso8601String(),
                'is_accepting' => $this->examPeriod->isAcceptingApplications(),
            ]),
            'form' => [
                'full_name_ar' => $this->full_name_ar,
                'full_name_en' => $this->full_name_en,
                'mother_name_ar' => $this->mother_name_ar,
                'birth_date' => $this->birth_date?->format('Y-m-d'),
                'gender' => $this->gender?->value,
                'national_id_number' => $this->national_id_number,
                'phone' => $this->phone,
                'contact_email' => $this->contact_email,
                'address' => $this->address,
                'governorate_code' => $this->governorate_code,
                'directorate_code' => $this->directorate_code,
                'school_name' => $this->school_name,
                'branch_code' => $this->branch_code,
                'category_code' => $this->category_code,
                'previous_exam_year' => $this->previous_exam_year,
            ],
            'labels' => [
                'governorate' => $referenceData->name(ReferenceType::Governorate, $this->governorate_code),
                'directorate' => $referenceData->name(ReferenceType::Directorate, $this->directorate_code),
                'branch' => $referenceData->name(ReferenceType::Branch, $this->branch_code),
                'category' => $referenceData->name(ReferenceType::Category, $this->category_code),
                'gender' => $this->gender !== null ? __('applications.gender.'.$this->gender->value) : null,
            ],
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'last_submitted_at' => $this->last_submitted_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'documents' => $this->whenLoaded('currentDocuments', fn (): array => ApplicationDocumentResource::collection($this->currentDocuments)->resolve($request)),
            'events' => $this->whenLoaded('events', fn (): array => ApplicationEventResource::collection($this->events)->resolve($request)),
            'assignee' => $this->whenLoaded('assignee', fn (): ?array => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
        ];
    }
}
