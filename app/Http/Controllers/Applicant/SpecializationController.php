<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Applications\SaveApplicationDraft;
use App\Actions\Applications\StartApplication;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ExamPeriod;
use App\Services\Audit\AuditLogger;
use App\Services\ReferenceData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The step right after registration: the student picks the branch and
 * specialization they apply for. The choice is saved into their draft for
 * the open exam period, so the application form starts with it. The
 * one-time access code flashed at registration is kept until the student
 * card shows it.
 */
class SpecializationController extends Controller
{
    private const ACCESS_CODE = 'student.access_code';

    public function __construct(private ReferenceData $referenceData) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isApplicant()) {
            return to_route('dashboard');
        }

        $request->session()->keep([self::ACCESS_CODE]);

        $period = $this->openPeriod();
        $application = $period !== null
            ? Application::query()->where('user_id', $user->id)->where('exam_period_id', $period->id)->first()
            : null;

        return Inertia::render('apply/specialization', [
            'period' => $period !== null ? [
                'name' => $period->localizedName(),
                'academic_year' => $period->academic_year,
            ] : null,
            'branches' => $period !== null ? $this->branches($period) : [],
            'selected' => [
                'branch_code' => $application?->branch_code,
                'category_code' => $application?->category_code,
            ],
            'locked' => $application !== null && ! $application->status->isEditableByApplicant(),
        ]);
    }

    public function store(
        Request $request,
        StartApplication $startApplication,
        SaveApplicationDraft $saveDraft,
        AuditLogger $audit,
    ): RedirectResponse {
        $request->session()->keep([self::ACCESS_CODE]);

        Gate::authorize('create', Application::class);

        $period = $this->openPeriod();

        if ($period === null) {
            return to_route('student.card');
        }

        $branches = $this->branches($period);
        $validated = $request->validate([
            'branch_code' => ['required', 'string', Rule::in(array_column($branches, 'code'))],
            'category_code' => ['required', 'string', Rule::in($this->categoryCodesOf($branches, (string) $request->input('branch_code')))],
        ], [
            'category_code.in' => __('applications.errors.specialization_branch'),
        ]);

        $application = $startApplication->handle($request->user(), $period);
        $application = $saveDraft->handle($application, $validated, $application->lock_version, $request->user());

        $audit->record('application.specialization_chosen', $application, $validated);

        return to_route('student.card');
    }

    /**
     * The period students apply to now: the open one closing soonest.
     */
    private function openPeriod(): ?ExamPeriod
    {
        return ExamPeriod::query()->acceptingApplications()->orderBy('closes_at')->first();
    }

    /**
     * Active branches eligible in the period, each with its eligible
     * specializations, in the authority's order.
     *
     * @return list<array{code: string, name: string, specializations: list<array{code: string, name: string}>}>
     */
    private function branches(ExamPeriod $period): array
    {
        $english = app()->getLocale() === 'en';
        $eligibleBranches = $period->eligible_branch_codes;
        $eligibleCategories = $period->eligible_category_codes;
        $branches = [];

        foreach ($this->referenceData->items(ReferenceType::Branch) as $branch) {
            if ($eligibleBranches !== null && ! in_array($branch['code'], $eligibleBranches, true)) {
                continue;
            }

            $specializations = [];

            foreach ($this->referenceData->items(ReferenceType::Category) as $category) {
                if ($category['parent_code'] !== $branch['code']
                    || ($eligibleCategories !== null && ! in_array($category['code'], $eligibleCategories, true))) {
                    continue;
                }

                $specializations[] = [
                    'code' => $category['code'],
                    'name' => $english ? $category['name_en'] : $category['name_ar'],
                ];
            }

            if ($specializations !== []) {
                $branches[] = [
                    'code' => $branch['code'],
                    'name' => $english ? $branch['name_en'] : $branch['name_ar'],
                    'specializations' => $specializations,
                ];
            }
        }

        return $branches;
    }

    /**
     * @param  list<array{code: string, specializations: list<array{code: string}>}>  $branches
     * @return list<string>
     */
    private function categoryCodesOf(array $branches, string $branchCode): array
    {
        foreach ($branches as $branch) {
            if ($branch['code'] === $branchCode) {
                return array_column($branch['specializations'], 'code');
            }
        }

        return [];
    }
}
