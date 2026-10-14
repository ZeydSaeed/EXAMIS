<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\Permission;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Assigns (or unassigns, with a null assignee) the reviewer responsible for
 * an application. Only active staff who can review may be assigned.
 */
class AssignApplication
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Application $application, ?User $assignee, User $actor): Application
    {
        if (! $actor->hasPermission(Permission::ApplicationsAssign)) {
            throw new AuthorizationException;
        }

        if ($assignee !== null && ! $assignee->hasPermission(Permission::ApplicationsReview)) {
            throw DomainRuleViolation::assigneeNotEligible();
        }

        return DB::transaction(function () use ($application, $assignee, $actor): Application {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [ApplicationStatus::Draft, ApplicationStatus::Withdrawn], true)) {
                throw DomainRuleViolation::invalidTransition();
            }

            if ($locked->assigned_to === $assignee?->id) {
                return $locked;
            }

            $previous = $locked->assigned_to;
            $locked->assigned_to = $assignee?->id;
            $locked->assigned_at = $assignee !== null ? now() : null;
            $locked->save();

            $locked->events()->create([
                'actor_id' => $actor->id,
                'type' => ApplicationEventType::Assigned,
                'metadata' => ['from' => $previous, 'to' => $assignee?->id],
            ]);

            $this->audit->record('application.assigned', $locked, ['from' => $previous, 'to' => $assignee?->id], $actor);

            return $locked;
        }, 3);
    }
}
