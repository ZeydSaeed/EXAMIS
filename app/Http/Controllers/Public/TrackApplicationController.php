<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Applications\ReferenceNumberGenerator;
use App\Services\Audit\AuditLogger;
use App\Services\Receipts\ReceiptRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Status lookup and form download without signing in, for students whose
 * one-time account has closed. The reference number alone is enough, as the
 * owner decided (ADR-0010): numbers are random, but one that is guessed or
 * shared opens that form. Rate limiting and the audit trail slow guessing down.
 */
class TrackApplicationController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('public/track', [
            'result' => $request->session()->get('track_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:32'],
        ]);

        $reference = Str::upper(trim($validated['reference_number']));

        $application = ReferenceNumberGenerator::isWellFormed($reference)
            ? Application::query()
                ->with('examPeriod')
                ->where('reference_number', $reference)
                ->first()
            : null;

        if ($application === null) {
            return back()->withErrors(['reference_number' => __('applications.track.not_found')])->onlyInput('reference_number');
        }

        return back()->with('track_result', [
            'reference_number' => $application->reference_number,
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'exam_period' => $application->examPeriod->localizedName(),
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'form_url' => URL::temporarySignedRoute('track.form', now()->addMinutes(15), $application),
        ]);
    }

    /**
     * The form as a file, from a link only the lookup above hands out.
     */
    public function form(Application $application, ReceiptRenderer $renderer, AuditLogger $audit): HttpResponse
    {
        abort_if($application->reference_number === null, 404);

        $pdf = $renderer->render($application);

        $audit->record('receipt.downloaded', $application, ['status' => $application->status->value, 'via' => 'track']);

        return $renderer->response($application, $pdf, download: true);
    }
}
