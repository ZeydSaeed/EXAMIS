<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Applications\TransitionApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransitionApplicationRequest;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ApplicationTransitionController extends Controller
{
    public function store(TransitionApplicationRequest $request, Application $application, TransitionApplication $transition): RedirectResponse
    {
        $transition->handle($application, $request->target(), $request->user(), $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('applications.transitioned')]);

        return to_route('admin.applications.show', $application);
    }
}
