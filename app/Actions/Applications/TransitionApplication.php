<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\Permission;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\User;
use App\Notifications\ApplicationStatusChanged;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Executes review decisions and withdrawals through the explicit state
 * machine (ADR-0004). Submissions go through SubmitApplication because they
 * carry numbering and completeness rules.
 *
 * The transition is validated against the row as locked inside the
 * transaction, so two reviewers acting at once cannot both succeed.
 */
class TransitionApplication
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Application $application, ApplicationStatus $target, User $actor, ?string $reason = null): Application
    {
        if ($target === ApplicationStatus::Submitted || $target === ApplicationStatus::Resubmitted) {
            throw DomainRuleViolation::invalidTransition();
        }

        $reason = filled($reason) ? trim((string) $reason) : null;

        return DB::transaction(function () use ($application, $target, $actor, $reason): Application {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $target) {
                return $locked;
            }

            if (! $from->canTransitionTo($target)) {
                throw DomainRuleViolation::invalidTransition();
            }

            $this->authorize($locked, $from, $target, $actor);

            if ($from->requiresReasonFor($target) && $reason === null) {
                throw DomainRuleViolation::reasonRequired();
            }

            $now = now();
            $locked->status = $target;
            $locked->lock_version++;

            match ($target) {
                ApplicationStatus::Accepted, ApplicationStatus::Rejected => $locked->decided_at = $now,
                ApplicationStatus::Withdrawn => $locked->withdrawn_at = $now,
                default => null,
            };

            if ($target === ApplicationStatus::UnderReview && $locked->assigned_to === null) {
                $locked->assigned_to = $actor->id;
                $locked->assigned_at = $now;
            }

            $locked->save();

            $locked->events()->create([
                'actor_id' => $actor->id,
                'type' => ApplicationEventType::StatusChanged,
                'from_status' => $from,
                'to_status' => $target,
                'reason' => $reason,
            ]);

            $this->audit->record('application.'.$target->value, $locked, ['from' => $from->value], $actor);

            if ($actor->id !== $locked->user_id) {
                $locked->user->notify((new ApplicationStatusChanged($locked, $target, $reason))->afterCommit());
            }

            return $locked;
        }, 3);
    }

    /**
     * Defence in depth: HTTP policies check this too, but the action must be
     * safe for any caller (jobs, console, future APIs).
     */
    private function authorize(Application $application, ApplicationStatus $from, ApplicationStatus $target, User $actor): void
    {
        $permission = $from->permissionFor($target);

        if ($permission === null) {
            if ($actor->id !== $application->user_id) {
                throw new AuthorizationException;
            }

            return;
        }

        if (! $actor->hasPermission($permission)) {
            throw new AuthorizationException;
        }

        $actsOnOthersWork = $application->assigned_to !== null && $application->assigned_to !== $actor->id;

        if ($actsOnOthersWork && ! $actor->hasPermission(Permission::ApplicationsViewAll)) {
            throw new AuthorizationException;
        }
    }
}
