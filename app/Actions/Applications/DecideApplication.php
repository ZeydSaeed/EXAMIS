<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The decision staff pick from the applications list. A list has no separate
 * "start review" step, so this walks the state machine for them: each step
 * goes through TransitionApplication, which keeps its rules, its event and its
 * audit record.
 */
class DecideApplication
{
    /**
     * @var list<ApplicationStatus>
     */
    public const DECISIONS = [
        ApplicationStatus::UnderReview,
        ApplicationStatus::Accepted,
        ApplicationStatus::Rejected,
        ApplicationStatus::RequiresCorrection,
        ApplicationStatus::FeesPaid,
    ];

    public function __construct(private TransitionApplication $transition) {}

    public function handle(Application $application, ApplicationStatus $decision, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($application, $decision, $actor, $reason): void {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $steps = $locked->status->stepsTo($decision);

            if ($steps === []) {
                throw DomainRuleViolation::invalidTransition();
            }

            foreach ($steps as $step) {
                // The message belongs to the decision, not to a stop on the way.
                $needed = $step === $decision || $locked->status->requiresReasonFor($step);

                $this->transition->handle($locked, $step, $actor, $needed ? $reason : null);
                $locked->refresh();
            }
        }, 3);
    }

    /**
     * The decisions this staff member may take on the application now.
     *
     * @return list<array{value: string, label: string, requires_reason: bool}>
     */
    public function options(Application $application, User $actor): array
    {
        $options = [];

        foreach (self::DECISIONS as $decision) {
            $steps = $application->status->stepsTo($decision);
            $from = $application->status;
            $allowed = $steps !== [];
            $requiresReason = false;

            foreach ($steps as $step) {
                $permission = $from->permissionFor($step);
                $allowed = $allowed && $permission !== null && $actor->hasPermission($permission);
                $requiresReason = $requiresReason || $from->requiresReasonFor($step);
                $from = $step;
            }

            if ($allowed) {
                $options[] = ['value' => $decision->value, 'label' => $this->label($application, $decision), 'requires_reason' => $requiresReason];
            }
        }

        return $options;
    }

    /**
     * Under review is shown for a sent application already, so choosing it
     * reads as the step it takes: starting the review, or going back to it.
     */
    private function label(Application $application, ApplicationStatus $decision): string
    {
        if ($decision !== ApplicationStatus::UnderReview) {
            return $decision->label();
        }

        return __('applications.actions.'.($application->status->isDecided() ? 'reopen' : 'under_review'));
    }
}
