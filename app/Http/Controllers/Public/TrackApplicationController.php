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
 * Status lookup without signing in. Requires the reference number AND the
 * applicant's birth date, is rate limited, and reveals status only.
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
            'birth_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $reference = Str::upper(trim($validated['reference_number']));

        $application = ReferenceNumberGenerator::isWellFormed($reference)
            ? Application::query()
                ->with('examPeriod')
                ->where('reference_number', $reference)
                ->whereDate('birth_date', $validated['birth_date'])
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
        ]);
    }
}
