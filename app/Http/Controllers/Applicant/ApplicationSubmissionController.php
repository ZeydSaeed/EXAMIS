<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Applications\SubmitApplication;
use App\Actions\Students\CloseStudentAccount;
use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationSubmissionController extends Controller
{
    /**
     * Printing the form is the student's last step (ADR-0010): the application
     * goes to the review committee, the one-time account closes and only the
     * final page with the PDF remains.
     */
    public function store(Request $request, Application $application, SubmitApplication $submitApplication, CloseStudentAccount $closeAccount): RedirectResponse
    {
        Gate::authorize('submit', $application);

        $request->validate([
            'declaration' => ['accepted'],
        ]);

        $application = $submitApplication->handle($application, $request->user());

        $closeAccount->handle($request->user(), $application);

        // The pages behind this one must not come back from the browser's history.
        Inertia::clearHistory();

        return to_route('applications.printed', $application);
    }

    public function printed(Request $request, Application $application): Response|RedirectResponse
    {
        Gate::authorize('view', $application);

        if (! $request->user()->isClosed()) {
            return to_route('applications.show', $application);
        }

        return Inertia::render('applicant/applications/printed', [
            'studentNumber' => $request->user()->student_number,
            'formUrl' => route('applications.receipt', $application),
        ]);
    }
}
