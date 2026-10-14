<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\User;
use App\Notifications\ApplicationStatusChanged;
use App\Services\Applications\ReferenceNumberGenerator;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Submits a draft (draft → submitted) or a correction (requires_correction →
 * resubmitted). Idempotent by state: repeating the call after success returns
 * the already-submitted application unchanged, so a client may safely retry
 * after a timeout (ADR-0005).
 */
class SubmitApplication
{
    public function __construct(
        private ReferenceNumberGenerator $referenceNumbers,
        private AuditLogger $audit,
    ) {}

    public function handle(Application $application, User $applicant): Application
    {
        return DB::transaction(function () use ($application, $applicant): Application {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            $target = match ($from) {
                ApplicationStatus::Draft => ApplicationStatus::Submitted,
                ApplicationStatus::RequiresCorrection => ApplicationStatus::Resubmitted,
                ApplicationStatus::Withdrawn => throw DomainRuleViolation::invalidTransition(),
                default => null,
            };

            if ($target === null) {
                return $locked;
            }

            $period = $locked->examPeriod;

            if ($from === ApplicationStatus::Draft && ! $period->isAcceptingApplications()) {
                throw DomainRuleViolation::periodClosed();
            }

            $locked->load('currentDocuments', 'examPeriod.documentTypes');

            if (! $locked->isReadyForSubmission()) {
                throw DomainRuleViolation::incomplete($locked->missingRequirements());
            }

            $this->assertEligible($locked);

            $now = now();

            if ($from === ApplicationStatus::Draft) {
                $locked->reference_number = $this->referenceNumbers->next($period);
                $locked->submitted_at = $now;
                $locked->declaration_accepted_at = $now;
            }

            $locked->status = $target;
            $locked->last_submitted_at = $now;
            $locked->lock_version++;
            $locked->save();

            $locked->events()->create([
                'actor_id' => $applicant->id,
                'type' => ApplicationEventType::StatusChanged,
                'from_status' => $from,
                'to_status' => $target,
            ]);

            $this->audit->record('application.'.$target->value, $locked, ['reference' => $locked->reference_number], $applicant);

            $locked->user->notify((new ApplicationStatusChanged($locked, $target))->afterCommit());

            return $locked;
        }, 3);
    }

    /**
     * Re-check eligibility at submission: period rules may have changed
     * after the draft was saved.
     */
    private function assertEligible(Application $application): void
    {
        $period = $application->examPeriod;

        if ($period->eligible_branch_codes !== null && ! in_array($application->branch_code, $period->eligible_branch_codes, true)) {
            throw DomainRuleViolation::notEligible('branch_code');
        }

        if ($period->eligible_category_codes !== null && ! in_array($application->category_code, $period->eligible_category_codes, true)) {
            throw DomainRuleViolation::notEligible('category_code');
        }
    }
}
