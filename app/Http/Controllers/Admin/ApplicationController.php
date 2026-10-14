<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\DocumentType;
use App\Models\ExamPeriod;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Application::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'period' => ['nullable', 'integer'],
            'assigned' => ['nullable', Rule::in(['me', 'unassigned'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        $applications = Application::query()
            ->visibleToStaff($user)
            ->with(['examPeriod:id,code,name_ar,name_en', 'assignee:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['period'] ?? null, fn (Builder $query, int|string $period) => $query->where('exam_period_id', $period))
            ->when(($filters['assigned'] ?? null) === 'me', fn (Builder $query) => $query->where('assigned_to', $user->id))
            ->when(($filters['assigned'] ?? null) === 'unassigned', fn (Builder $query) => $query->whereNull('assigned_to'))
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $this->search($query, $term))
            // Visible applications are never drafts, so submitted_at is always
            // set (DB CHECK); plain column order lets the queue index serve it.
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Application $application): array => [
                'uuid' => $application->uuid,
                'reference_number' => $application->reference_number,
                'full_name_ar' => $application->full_name_ar,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'exam_period' => $application->examPeriod->localizedName(),
                'assignee' => $application->assignee?->name,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/applications/index', [
            'applications' => $applications,
            'filters' => $filters,
            'periods' => ExamPeriod::query()->latest('opens_at')->get(['id', 'name_ar', 'name_en'])
                ->map(fn (ExamPeriod $period): array => ['id' => $period->id, 'name' => $period->localizedName()]),
            'statuses' => collect(ApplicationStatus::cases())
                ->reject(fn (ApplicationStatus $status): bool => $status === ApplicationStatus::Draft)
                ->map(fn (ApplicationStatus $status): array => ['value' => $status->value, 'label' => $status->label()])
                ->values(),
        ]);
    }

    public function show(Request $request, Application $application, AuditLogger $audit): Response
    {
        Gate::authorize('view', $application);

        $user = $request->user();
        $application->load([
            'examPeriod.documentTypes',
            'assignee:id,name',
            'currentDocuments.documentType',
            'events' => fn ($query) => $query->with('actor:id,name')->orderBy('id'),
        ]);

        $audit->record('application.viewed', $application);

        $transitions = collect(ApplicationStatus::transitions()[$application->status->value])
            ->filter(fn (ApplicationStatus $target): bool => $application->status->permissionFor($target) !== null
                && Gate::allows('transition', [$application, $target]))
            ->map(fn (ApplicationStatus $target): array => [
                'target' => $target->value,
                'label' => __('applications.actions.'.$target->value),
                'requires_reason' => $application->status->requiresReasonFor($target),
            ])
            ->values();

        return Inertia::render('admin/applications/show', [
            'application' => ApplicationResource::make($application)->resolve(),
            'documentTypes' => $application->examPeriod->documentTypes
                ->map(fn (DocumentType $type): array => [
                    'code' => $type->code,
                    'name' => $type->localizedName(),
                    'required' => (bool) $type->pivot->is_required,
                ])->values(),
            'transitions' => $transitions,
            'canViewDocuments' => $user->hasPermission(Permission::DocumentsView),
            'canAssign' => Gate::allows('assign', $application),
            'reviewers' => Gate::allows('assign', $application) ? $this->reviewers() : [],
        ]);
    }

    /**
     * Exact matches on indexed columns, otherwise a prefix match on the name.
     *
     * @param  Builder<Application>  $query
     */
    private function search(Builder $query, string $term): void
    {
        $term = trim($term);

        $query->where(function (Builder $query) use ($term): void {
            $query->where('reference_number', Str::upper($term))
                ->orWhere('national_id_number', $term)
                ->orWhere('full_name_ar', 'like', str_replace(['%', '_'], ['\%', '\_'], $term).'%');
        });
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function reviewers(): array
    {
        return array_values(User::query()
            ->staff()
            ->whereNull('deactivated_at')
            ->with('roleAssignments')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission(Permission::ApplicationsReview))
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->all());
    }
}
