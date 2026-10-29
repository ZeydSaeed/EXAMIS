<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves applicant form data with optimistic locking: a save based on a stale
 * `lock_version` is rejected instead of silently overwriting another
 * session's edits (ADR-0005).
 */
class SaveApplicationDraft
{
    /**
     * @param  array<string, mixed>  $data  validated form fields only
     */
    public function handle(Application $application, array $data, int $expectedVersion, User $actor): Application
    {
        return DB::transaction(function () use ($application, $data, $expectedVersion, $actor): Application {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isEditableByApplicant()) {
                throw DomainRuleViolation::notEditable();
            }

            if ($locked->lock_version !== $expectedVersion) {
                throw DomainRuleViolation::staleVersion();
            }

            if (($locked->status === ApplicationStatus::Draft || $locked->status->isAwaitingReview())
                && ! $locked->examPeriod->isAcceptingApplications()) {
                throw DomainRuleViolation::periodClosed();
            }

            $locked->fill(array_intersect_key($data, array_flip(Application::FORM_FIELDS)));
            $changedFields = array_keys($locked->getDirty());

            if ($changedFields === []) {
                return $locked;
            }

            // A submitted application must stay complete while it is edited.
            if ($locked->status->isAwaitingReview()) {
                $blank = $locked->missingFields();

                if ($blank !== []) {
                    throw DomainRuleViolation::requiredAfterSubmission($blank);
                }
            }

            $locked->lock_version++;
            $locked->save();

            // Changes after submission are shown to reviewers in the history.
            if ($locked->status !== ApplicationStatus::Draft) {
                $locked->events()->create([
                    'actor_id' => $actor->id,
                    'type' => ApplicationEventType::DataCorrected,
                    'metadata' => ['fields' => $changedFields],
                ]);
            }

            return $locked;
        }, 3);
    }
}
