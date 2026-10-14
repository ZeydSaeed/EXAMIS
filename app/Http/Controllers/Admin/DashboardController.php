<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ExamPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Counts are computed with one grouped query over the applications the
     * viewer may see, so reviewers only see numbers for their own queue.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        /** @var array<string, int> $byStatus */
        $byStatus = Application::query()
            ->visibleToStaff($user)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        $statusCounts = collect(ApplicationStatus::cases())
            ->reject(fn (ApplicationStatus $status): bool => $status === ApplicationStatus::Draft)
            ->map(fn (ApplicationStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $byStatus[$status->value] ?? 0,
            ])
            ->values();

        $myQueue = Application::query()
            ->where('assigned_to', $user->id)
            ->where('status', ApplicationStatus::UnderReview)
            ->count();

        $periods = ExamPeriod::query()
            ->whereIn('status', ['open', 'closed'])
            ->latest('opens_at')
            ->limit(5)
            ->get()
            ->map(fn (ExamPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->localizedName(),
                'status' => $period->status->value,
                'closes_at' => $period->closes_at->toIso8601String(),
            ]);

        return Inertia::render('admin/dashboard', [
            'statusCounts' => $statusCounts,
            'myQueue' => $myQueue,
            'periods' => $periods,
        ]);
    }
}
