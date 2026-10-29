<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\Permission;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ExamPeriod;
use App\Services\Audit\AuditLogger;
use App\Services\ReferenceData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReferenceData $referenceData): Response
    {
        $periodId = $request->integer('period') ?: ExamPeriod::query()->latest('opens_at')->value('id');

        $base = fn () => Application::query()
            ->where('exam_period_id', $periodId)
            ->where('status', '!=', ApplicationStatus::Draft);

        $grouped = fn (string $column) => $base()
            ->select($column.' as code')
            ->selectRaw('count(*) as aggregate')
            ->groupBy($column)
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (Application $row): array => ['code' => $row->getAttribute('code'), 'count' => (int) $row->getAttribute('aggregate')]);

        return Inertia::render('admin/reports/index', [
            'periodId' => $periodId,
            'periods' => ExamPeriod::query()->latest('opens_at')->get(['id', 'name_ar', 'name_en'])
                ->map(fn (ExamPeriod $period): array => ['id' => $period->id, 'name' => $period->localizedName()]),
            'byStatus' => $grouped('status')->map(fn (array $row): array => [...$row, 'label' => ApplicationStatus::from($row['code'])->label()]),
            'bySpecialization' => $grouped('category_code')->map(fn (array $row): array => [...$row, 'label' => $referenceData->name(ReferenceType::Category, $row['code'])]),
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

        $audit->record('report.exported', $period, ['code' => $period->code]);

        return response()->streamDownload(function () use ($period, $referenceData): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new RuntimeException('Unable to open the output stream.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'reference_number', 'status', 'full_name_ar', 'mother_name_ar', 'gender', 'birth_date', 'phone',
                'district', 'mahalla', 'alley', 'house_number', 'last_school', 'school_governorate',
                'branch', 'specialization', 'submitted_at',
            ], escape: '');

            Application::query()
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
                        (string) $application->phone,
                        (string) $application->district,
                        (string) $application->mahalla,
                        (string) $application->alley,
                        (string) $application->house_number,
                        (string) $application->school_name,
                        (string) $referenceData->name(ReferenceType::Governorate, $application->school_governorate_code),
                        (string) $referenceData->name(ReferenceType::Branch, $application->branch_code),
                        (string) $referenceData->name(ReferenceType::Category, $application->category_code),
                        (string) $application->submitted_at?->toIso8601String(),
                    ]), escape: '');
                });

            fclose($output);
        }, 'applications-'.$period->code.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * Prevent CSV/formula injection when the file is opened in a spreadsheet.
     */
    private function neutralizeFormula(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
