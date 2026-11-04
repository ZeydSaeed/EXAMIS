<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Applications\DecideApplication;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\MessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ApplicationDecisionController extends Controller
{
    public function store(Request $request, Application $application, DecideApplication $decide): RedirectResponse
    {
        Gate::authorize('view', $application);

        $validated = $request->validate([
            'target' => ['required', Rule::enum(ApplicationStatus::class)->only(DecideApplication::DECISIONS)],
            'reason' => ['nullable', 'string', 'max:2000', 'not_regex:/\{'.MessageTemplate::GAP.'\}/u'],
        ], ['reason.not_regex' => __('admin.errors.fill_gaps')]);

        $decide->handle($application, ApplicationStatus::from($validated['target']), $request->user(), $validated['reason'] ?? null);

        return back();
    }
}
