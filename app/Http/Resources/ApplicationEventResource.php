<?php

namespace App\Http\Resources;

use App\Models\ApplicationEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Applicants see that "a reviewer" acted, not which staff member; staff see names.
 *
 * @mixin ApplicationEvent
 */
class ApplicationEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerIsStaff = (bool) $request->user()?->isStaff();
        $actorIsOwner = $this->actor_id !== null && $this->actor_id === $this->application->user_id;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => __('applications.events.'.$this->type->value),
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status?->value,
            'to_status_label' => $this->to_status?->label(),
            'reason' => $this->reason,
            'actor' => match (true) {
                $this->actor_id === null => __('applications.actors.system'),
                $actorIsOwner => __('applications.actors.applicant'),
                $viewerIsStaff => $this->actor?->name,
                default => __('applications.actors.staff'),
            },
            'metadata' => $viewerIsStaff ? $this->metadata : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
