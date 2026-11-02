<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Audit\AuditLogger;
use App\Services\ReferenceData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The student information staff keep in Excel. The page and the file show the
 * same columns, for every submitted application or those sent between two
 * dates. Contains personal data, so both are limited to `applications.view_all`
 * (routes/staff.php) and each download is audited.
 */
class StudentExportController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * Timestamps are stored in UTC; staff pick dates as they read them in Iraq.
     */
    private const LOCAL_TIMEZONE = 'Asia/Baghdad';

    public function index(Request $request, ReferenceData $referenceData): Response
    {
        $filters = $request->validate([
            ...$this->rules(),
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ], [], $this->attributes());

        $students = $this->query($request, $filters)
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? self::PER_PAGE))
            ->withQueryString()
            ->through(fn (Application $application): array => [
                'uuid' => $application->uuid,
                'cells' => $this->cells($application, $referenceData),
            ]);

        return Inertia::render('admin/student-export/index', [
            'columns' => $this->columns(),
            'students' => $students,
            'filters' => $filters,
        ]);
    }

    /**
     * Streams the workbook with lazy, chunked reads so memory stays flat for
     * any number of students.
     */
    public function download(Request $request, AuditLogger $audit, ReferenceData $referenceData): StreamedResponse
    {
        $filters = $request->validate($this->rules(), [], $this->attributes());

        $audit->record('students.exported', null, ['filters' => $filters]);

        return response()->streamDownload(function () use ($request, $filters, $referenceData): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $sheet = $writer->getCurrentSheet();
            $sheet->setName(__('ui.student_export.sheet'));
            $sheet->setSheetView((new SheetView)->withFreezeRow(2)->withRightToLeft(app()->getLocale() !== 'en'));

            $writer->addRow($this->row([__('ui.admin.serial'), ...$this->columns()], new Style(fontBold: true)));

            $serial = 0;

            $this->query($request, $filters)
                ->lazyById(500)
                ->each(function (Application $application) use ($writer, $referenceData, &$serial): void {
                    $writer->addRow($this->row([(string) ++$serial, ...$this->cells($application, $referenceData)]));
                });

            $writer->close();
        }, 'students-'.now(self::LOCAL_TIMEZONE)->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return ['from' => __('ui.student_export.from'), 'to' => __('ui.student_export.to')];
    }

    /**
     * Whole days at both ends, so a range of one day covers that day.
     *
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return Builder<Application>
     */
    private function query(Request $request, array $filters): Builder
    {
        return Application::query()
            ->visibleToStaff($request->user())
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where(
                'submitted_at', '>=', CarbonImmutable::parse($from, self::LOCAL_TIMEZONE)->utc(),
            ))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where(
                'submitted_at', '<', CarbonImmutable::parse($to, self::LOCAL_TIMEZONE)->addDay()->utc(),
            ));
    }

    /**
     * @return list<string>
     */
    private function columns(): array
    {
        return array_map(__(...), [
            'ui.fields.student_name',
            'ui.fields.father_name',
            'ui.fields.grandfather_name',
            'ui.fields.great_grandfather_name',
            'ui.fields.branch',
            'ui.fields.category',
            'ui.fields.mother_first_name',
            'ui.fields.mother_father_name',
            'ui.fields.mother_grandfather_name',
            'ui.fields.birth_date',
            'ui.fields.birth_place',
            'ui.fields.district',
            'ui.fields.mahalla',
            'ui.fields.alley',
            'ui.fields.house_number',
            'ui.student_export.mobile',
            'ui.fields.school_name',
            'ui.fields.school_governorate',
        ]);
    }

    /**
     * One value per entry of columns().
     *
     * @return list<string|null>
     */
    private function cells(Application $application, ReferenceData $referenceData): array
    {
        return [
            $application->student_name,
            $application->father_name,
            $application->grandfather_name,
            $application->great_grandfather_name,
            $referenceData->name(ReferenceType::Branch, $application->branch_code),
            $referenceData->name(ReferenceType::Category, $application->category_code),
            $application->mother_first_name,
            $application->mother_father_name,
            $application->mother_grandfather_name,
            $application->birth_date?->format('Y-m-d'),
            $application->birthPlace(),
            $application->district,
            $application->mahalla,
            $application->alley,
            $application->house_number,
            $application->phone,
            $application->school_name,
            $application->schoolGovernorate(),
        ];
    }

    /**
     * Every cell is written as text: students type these values, so one that
     * starts with "=" must not become a formula, and a mobile number keeps its
     * leading zero.
     *
     * @param  list<string|null>  $values
     */
    private function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn (?string $value): StringCell => new StringCell((string) $value, $style), $values));
    }
}
