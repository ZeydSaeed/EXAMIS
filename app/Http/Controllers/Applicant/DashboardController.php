<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\ExamPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Applicant home: their applications plus periods currently open.
     * Staff are sent to the administration area.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isStaff()) {
            return to_route('admin.dashboard');
        }

        $applications = $user->applications()
            ->with('examPeriod')
            ->latest('id')
            ->get();

        $appliedPeriodIds = $applications->pluck('exam_period_id')->all();

        $openPeriods = ExamPeriod::query()
            ->acceptingApplications()
            ->orderBy('closes_at')
            ->get()
            ->map(fn (ExamPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->localizedName(),
                'academic_year' => $period->academic_year,
                'closes_at' => $period->closes_at->toIso8601String(),
                'has_applied' => in_array($period->id, $appliedPeriodIds, true),
            ]);

        return Inertia::render('applicant/dashboard', [
            'applications' => ApplicationResource::collection($applications)->resolve(),
            'openPeriods' => $openPeriods,
        ]);
    }
}
