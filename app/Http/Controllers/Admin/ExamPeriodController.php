<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExamPeriodStatus;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExamPeriodRequest;
use App\Models\DocumentType;
use App\Models\ExamPeriod;
use App\Services\Audit\AuditLogger;
use App\Services\ReferenceData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExamPeriodController extends Controller
{
    public function index(): Response
    {
        $periods = ExamPeriod::query()
            ->withCount('applications')
            ->latest('opens_at')
            ->paginate(20)
            ->through(fn (ExamPeriod $period): array => [
                'id' => $period->id,
                'code' => $period->code,
                'name' => $period->localizedName(),
                'academic_year' => $period->academic_year,
                'status' => $period->status->value,
                'opens_at' => $period->opens_at->toIso8601String(),
                'closes_at' => $period->closes_at->toIso8601String(),
                'applications_count' => $period->applications_count,
            ]);

        return Inertia::render('admin/periods/index', ['periods' => $periods]);
    }

    public function create(ReferenceData $referenceData): Response
    {
        return Inertia::render('admin/periods/form', [
            'period' => null,
            ...$this->formOptions($referenceData),
        ]);
    }

    public function store(ExamPeriodRequest $request, AuditLogger $audit): RedirectResponse
    {
        $period = DB::transaction(function () use ($request): ExamPeriod {
            $period = ExamPeriod::create($request->periodAttributes());
            $period->documentTypes()->sync($request->documentRequirements());

            return $period;
        });

        $audit->record('period.created', $period, ['code' => $period->code]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.saved')]);

        return to_route('admin.periods.index');
    }

    public function edit(ExamPeriod $period, ReferenceData $referenceData): Response
    {
        $period->load('documentTypes');

        return Inertia::render('admin/periods/form', [
            'period' => [
                ...$period->only(['id', 'code', 'name_ar', 'name_en', 'academic_year', 'instructions_ar', 'instructions_en']),
                'status' => $period->status->value,
                'opens_at' => $period->opens_at->format('Y-m-d\TH:i'),
                'closes_at' => $period->closes_at->format('Y-m-d\TH:i'),
                'eligible_branch_codes' => $period->eligible_branch_codes ?? [],
                'eligible_category_codes' => $period->eligible_category_codes ?? [],
                'documents' => $period->documentTypes->map(fn (DocumentType $type): array => [
                    'document_type_id' => $type->id,
                    'is_required' => (bool) $type->pivot->is_required,
                ])->values(),
            ],
            ...$this->formOptions($referenceData),
        ]);
    }

    public function update(ExamPeriodRequest $request, ExamPeriod $period, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $period): void {
            $period->update($request->periodAttributes());
            $period->documentTypes()->sync($request->documentRequirements());
        });

        $audit->record('period.updated', $period, ['changed' => array_keys($period->getChanges())]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.saved')]);

        return to_route('admin.periods.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(ReferenceData $referenceData): array
    {
        return [
            'statuses' => array_map(fn (ExamPeriodStatus $status): string => $status->value, ExamPeriodStatus::cases()),
            'branches' => $referenceData->items(ReferenceType::Branch),
            'categories' => $referenceData->items(ReferenceType::Category),
            'documentTypes' => DocumentType::query()->orderBy('id')->get()
                ->map(fn (DocumentType $type): array => ['id' => $type->id, 'code' => $type->code, 'name' => $type->localizedName()]),
        ];
    }
}
