<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Applications\TransitionApplication;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ApplicationWithdrawalController extends Controller
{
    public function store(Request $request, Application $application, TransitionApplication $transition): RedirectResponse
    {
        Gate::authorize('withdraw', $application);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'confirm' => ['accepted'],
        ]);

        $transition->handle($application, ApplicationStatus::Withdrawn, $request->user(), $validated['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('applications.withdrawn')]);

        return to_route('applications.show', $application);
    }
}
