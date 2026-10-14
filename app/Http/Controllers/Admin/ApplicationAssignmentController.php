<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Applications\AssignApplication;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ApplicationAssignmentController extends Controller
{
    public function store(Request $request, Application $application, AssignApplication $assign): RedirectResponse
    {
        Gate::authorize('assign', $application);

        $validated = $request->validate([
            'assignee_id' => ['nullable', 'integer'],
        ]);

        $assignee = isset($validated['assignee_id'])
            ? User::query()->staff()->with('roleAssignments')->whereKey((int) $validated['assignee_id'])->firstOrFail()
            : null;

        $assign->handle($application, $assignee, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('applications.assigned')]);

        return to_route('admin.applications.show', $application);
    }
}
