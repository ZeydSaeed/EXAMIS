<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Applications\SubmitApplication;
use App\Actions\Students\CloseStudentAccount;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Receipts\ReceiptRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationSubmissionController extends Controller
{
    /**
     * The application goes to the review committee and only the final page
     * with the PDF remains (ADR-0010). The account stays open until the form
     * has been printed or downloaded.
     */
    public function store(Request $request, Application $application, SubmitApplication $submitApplication, ReceiptRenderer $renderer): RedirectResponse
    {
        Gate::authorize('submit', $application);

        $request->validate([
            'declaration' => ['accepted'],
        ]);

        $application = $submitApplication->handle($application, $request->user());

        $renderer->warm($application);

        // The pages behind this one must not come back from the browser's history.
        Inertia::clearHistory();

        return to_route('applications.printed', $application);
    }

    public function printed(Request $request, Application $application, ReceiptRenderer $renderer): Response|RedirectResponse
    {
        Gate::authorize('finishPrinting', $application);

        if ($application->waitsOnStudent()) {
            return to_route('applications.show', $application);
        }

        return Inertia::render('applicant/applications/printed', [
            'studentNumber' => $request->user()->student_number,
            'formUrl' => route('applications.receipt', $application),
            'fileName' => $renderer->filename($application).'.pdf',
            'finishUrl' => route('applications.finish', $application),
        ]);
    }

    /**
     * The student confirmed the form was printed or downloaded, which browsers
     * cannot report themselves. The account closes here and the next request
     * ends the session (ADR-0010).
     */
    public function finish(Request $request, Application $application, CloseStudentAccount $closeAccount): RedirectResponse
    {
        Gate::authorize('finishPrinting', $application);

        $closeAccount->handle($request->user(), $application);

        return to_route('applications.printed', $application);
    }
}
