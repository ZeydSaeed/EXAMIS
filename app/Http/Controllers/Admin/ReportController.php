<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\Permission;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ExamPeriod;
use App\Services\Audit\AuditLogger;
use App\Services\ReferenceData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * The filters narrow every table and the export alike. The specialization
     * table also splits each row by gender, so both can be read at a glance.
     */
    public function index(Request $request, ReferenceData $referenceData): Response
    {
        $filters = $this->filters($request);
        $periodId = $request->integer('period') ?: ExamPeriod::query()->latest('opens_at')->value('id');

        $base = fn () => Application::query()
            ->where('exam_period_id', $periodId)
            ->where('status', '!=', ApplicationStatus::Draft);

        $filtered = fn () => $this->applyFilters($base(), $filters);

        $grouped = fn (string $column) => $filtered()
            ->select($column.' as code')
            ->selectRaw('count(*) as aggregate')
            ->groupBy($column)
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (Application $row): array => ['code' => $row->getAttribute('code'), 'count' => (int) $row->getAttribute('aggregate')]);

        $bySpecialization = $filtered()
            ->select('category_code as code')
            ->selectRaw('count(*) as aggregate')
            ->selectRaw('sum(case when gender = ? then 1 else 0 end) as males', [Gender::Male->value])
            ->selectRaw('sum(case when gender = ? then 1 else 0 end) as females', [Gender::Female->value])
            ->groupBy('category_code')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (Application $row): array => [
                'code' => $row->getAttribute('code'),
                'label' => $referenceData->name(ReferenceType::Category, $row->getAttribute('code')),
                'count' => (int) $row->getAttribute('aggregate'),
                'males' => (int) $row->getAttribute('males'),
                'females' => (int) $row->getAttribute('females'),
            ]);

        return Inertia::render('admin/reports/index', [
            'periodId' => $periodId,
            'filters' => $filters,
            'periods' => ExamPeriod::query()->latest('opens_at')->get(['id', 'name_ar', 'name_en'])
                ->map(fn (ExamPeriod $period): array => ['id' => $period->id, 'name' => $period->localizedName()]),
            'specializations' => $base()->whereNotNull('category_code')->distinct()->pluck('category_code')
                ->map(fn (string $code): array => ['value' => $code, 'label' => (string) $referenceData->name(ReferenceType::Category, $code)])
                ->sortBy('label')
                ->values(),
            'byStatus' => $grouped('status')->map(fn (array $row): array => [...$row, 'label' => ApplicationStatus::from($row['code'])->label()]),
            'bySpecialization' => $bySpecialization,
            'byBranch' => $grouped('branch_code')->map(fn (array $row): array => [...$row, 'label' => $referenceData->name(ReferenceType::Branch, $row['code'])]),
            'drafts' => Application::query()->where('exam_period_id', $periodId)->where('status', ApplicationStatus::Draft)->count(),
        ]);
    }

    /**
     * Streams a CSV with lazy, chunked reads so memory stays flat for any
     * period size. Contains personal data, so it is permission-gated and audited.
     */
    public function export(Request $request, ExamPeriod $period, AuditLogger $audit, ReferenceData $referenceData): StreamedResponse
    {
        Gate::authorize(Permission::ApplicationsViewAll->value);

        $filters = $this->filters($request);

        $audit->record('report.exported', $period, ['code' => $period->code, 'filters' => $filters]);

        return response()->streamDownload(function () use ($period, $filters, $referenceData): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new RuntimeException('Unable to open the output stream.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'reference_number', 'status', 'full_name_ar', 'mother_name_ar', 'gender', 'birth_date', 'birth_place', 'phone',
                'district', 'mahalla', 'alley', 'house_number', 'last_school', 'school_governorate',
                'branch', 'specialization', 'submitted_at',
            ], escape: '');

            $this->applyFilters(Application::query(), $filters)
                ->where('exam_period_id', $period->id)
                ->where('status', '!=', ApplicationStatus::Draft)
                ->orderBy('id')
                ->lazyById(500)
                ->each(function (Application $application) use ($output, $referenceData): void {
                    fputcsv($output, array_map($this->neutralizeFormula(...), [
                        (string) $application->reference_number,
                        $application->status->value,
                        (string) $application->full_name_ar,
                        (string) $application->mother_name_ar,
                        (string) $application->gender?->value,
                        (string) $application->birth_date?->format('Y-m-d'),
                        (string) $application->birthPlace(),
                        (string) $application->phone,
                        (string) $application->district,
                        (string) $application->mahalla,
                        (string) $application->alley,
                        (string) $application->house_number,
                        (string) $application->school_name,
                        (string) $application->schoolGovernorate(),
                        (string) $referenceData->name(ReferenceType::Branch, $application->branch_code),
                        (string) $referenceData->name(ReferenceType::Category, $application->category_code),
                        (string) $application->submitted_at?->toIso8601String(),
                    ]), escape: '');
                });

            fclose($output);
        }, 'applications-'.$period->code.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * @return array{gender?: string|null, category?: string|null}
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'category' => ['nullable', 'string', 'max:40'],
        ]);
    }

    /**
     * @param  Builder<Application>  $query
     * @param  array{gender?: string|null, category?: string|null}  $filters
     * @return Builder<Application>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['gender'] ?? null, fn (Builder $query, string $gender) => $query->where('gender', $gender))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category_code', $category));
    }

    /**
     * Prevent CSV/formula injection when the file is opened in a spreadsheet.
     */
    private function neutralizeFormula(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
