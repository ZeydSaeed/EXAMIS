<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Applications\SubmitApplication;
use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ApplicationSubmissionController extends Controller
{
    /**
     * Submit or resubmit. Safe to retry: an already submitted application is
     * returned unchanged and the applicant lands on the same confirmation.
     */
    public function store(Request $request, Application $application, SubmitApplication $submitApplication): RedirectResponse
    {
        Gate::authorize('submit', $application);

        $request->validate([
            'declaration' => ['accepted'],
        ]);

        $application = $submitApplication->handle($application, $request->user());

        Inertia::flash('submitted', ['reference_number' => $application->reference_number]);

        return to_route('applications.show', $application);
    }
}
