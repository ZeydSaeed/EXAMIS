<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ApplicationDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ApplicationDocumentPolicy
{
    /**
     * A document is served only after verification, and only to the owning
     * applicant or to staff who can see the application and view documents.
     */
    public function view(User $user, ApplicationDocument $document): Response
    {
        $application = $document->application;

        $isOwner = $user->isApplicant() && $application->user_id === $user->id;
        $isAuthorizedStaff = $user->hasPermission(Permission::DocumentsView) && $application->isVisibleToStaff($user);

        if (! $isOwner && ! $isAuthorizedStaff) {
            return Response::denyAsNotFound();
        }

        return $document->isDownloadable() ? Response::allow() : Response::deny(__('documents.errors.not_available'));
    }
}
