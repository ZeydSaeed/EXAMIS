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
            'full_name' => $this->full_name_ar,
            'mother_name' => $this->mother_name_ar,
            'form' => [
                'student_name' => $this->student_name,
                'father_name' => $this->father_name,
                'grandfather_name' => $this->grandfather_name,
                'great_grandfather_name' => $this->great_grandfather_name,
                'mother_first_name' => $this->mother_first_name,
                'mother_father_name' => $this->mother_father_name,
                'birth_date' => $this->birth_date?->format('Y-m-d'),
                'gender' => $this->gender?->value,
                'national_id_number' => $this->national_id_number,
                'phone' => $this->phone,
                'district' => $this->district,
                'mahalla' => $this->mahalla,
                'alley' => $this->alley,
                'house_number' => $this->house_number,
                'school_name' => $this->school_name,
                'school_governorate_code' => $this->school_governorate_code,
                'branch_code' => $this->branch_code,
                'category_code' => $this->category_code,
                'other_info' => $this->other_info,
            ],
            'labels' => [
                'governorate' => $referenceData->name(ReferenceType::Governorate, $this->governorate_code),
                'school_governorate' => $referenceData->name(ReferenceType::Governorate, $this->school_governorate_code),
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
