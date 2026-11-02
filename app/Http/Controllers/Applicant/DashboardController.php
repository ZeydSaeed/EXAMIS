<?php

namespace App\Http\Controllers\Applicant;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Where signing in lands. Nobody stays here: a student goes straight to
     * their next step, because a one-time account has one application and
     * nothing to choose between.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $application = $user->applications()->latest('id')->first();

        if ($application === null) {
            return to_route('apply.specialization');
        }

        if ($application->status === ApplicationStatus::Draft || $application->waitsOnStudent()) {
            return to_route('applications.edit', $application);
        }

        return to_route('applications.printed', $application);
    }
}
