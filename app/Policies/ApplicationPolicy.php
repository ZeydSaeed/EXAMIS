<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Enums\Permission;
use App\Models\Application;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Applicants only ever see their own applications; anything else answers 404
 * so the existence of another applicant's record is never confirmed.
 */
class ApplicationPolicy
{
    public function create(User $user): bool
    {
        return $user->isApplicant() && $user->isActive();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ApplicationsView);
    }

    public function view(User $user, Application $application): Response
    {
        return $this->isOwner($user, $application) || $application->isVisibleToStaff($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Application $application): Response
    {
        if (! $this->isOwner($user, $application)) {
            return Response::denyAsNotFound();
        }

        return $application->status->isEditableByApplicant()
            ? Response::allow()
            : Response::deny(__('applications.errors.not_editable'));
    }

    public function submit(User $user, Application $application): Response
    {
        return $this->isOwner($user, $application) ? Response::allow() : Response::denyAsNotFound();
    }

    public function withdraw(User $user, Application $application): Response
    {
        if (! $this->isOwner($user, $application)) {
            return Response::denyAsNotFound();
        }

        return $application->status->canTransitionTo(ApplicationStatus::Withdrawn)
            ? Response::allow()
            : Response::deny(__('applications.errors.invalid_transition'));
    }

    /**
     * Staff review transitions (start review, request correction, decide, reopen).
     */
    public function transition(User $user, Application $application, ApplicationStatus $target): Response
    {
        if (! $application->isVisibleToStaff($user)) {
            return Response::denyAsNotFound();
        }

        $permission = $application->status->permissionFor($target);

        return $permission !== null && $user->hasPermission($permission)
            ? Response::allow()
            : Response::deny();
    }

    public function assign(User $user, Application $application): Response
    {
        if (! $application->isVisibleToStaff($user)) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission(Permission::ApplicationsAssign) ? Response::allow() : Response::deny();
    }

    public function downloadReceipt(User $user, Application $application): Response
    {
        if (! $this->isOwner($user, $application) && ! $application->isVisibleToStaff($user)) {
            return Response::denyAsNotFound();
        }

        return $application->reference_number !== null ? Response::allow() : Response::deny();
    }

    private function isOwner(User $user, Application $application): bool
    {
        return $user->isApplicant() && $application->user_id === $user->id;
    }
}
