<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Applications\ReferenceNumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Status lookup without signing in, by reference number alone. It stops with
 * the account: once the form is printed nothing can be tracked or printed
 * again (ADR-0010).
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
                ->with(['examPeriod', 'user'])
                ->where('reference_number', $reference)
                ->first()
            : null;

        if ($application === null) {
            return back()->withErrors(['reference_number' => __('applications.track.not_found')])->onlyInput('reference_number');
        }

        if ($application->user->isClosed()) {
            return back()->withErrors(['reference_number' => __('applications.track.closed')])->onlyInput('reference_number');
        }

        return back()->with('track_result', [
            'reference_number' => $application->reference_number,
            'status' => $application->status->value,
            'status_label' => $application->status->shownAs()->label(),
            'exam_period' => $application->examPeriod->localizedName(),
            'submitted_at' => $application->submitted_at?->toIso8601String(),
        ]);
    }
}
