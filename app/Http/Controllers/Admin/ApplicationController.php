<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Applications\DecideApplication;
use App\Enums\ApplicationStatus;
use App\Enums\Permission;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\DocumentType;
use App\Models\ExamPeriod;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\ReferenceData;
use App\Services\Students\StudentIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * Arabic letter variants folded to one form so "احمد" also finds "أحمد".
     *
     * @var array<string, string>
     */
    private const ARABIC_FOLDS = ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه'];

    public function index(Request $request, DecideApplication $decide, ReferenceData $referenceData): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Application::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'period' => ['nullable', 'integer'],
            'assigned' => ['nullable', Rule::in(['me', 'unassigned'])],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $user = $request->user();

        $scanned = $this->scannedApplication($filters['q'] ?? null, $user);

        if ($scanned !== null) {
            return to_route('admin.applications.show', $scanned);
        }

        $applications = Application::query()
            ->visibleToStaff($user)
            ->with('user:id,student_number')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->whereIn('status', ApplicationStatus::from($status)->shownAs()->shownFrom()))
            ->when($filters['period'] ?? null, fn (Builder $query, int|string $period) => $query->where('exam_period_id', $period))
            ->when(($filters['assigned'] ?? null) === 'me', fn (Builder $query) => $query->where('assigned_to', $user->id))
            ->when(($filters['assigned'] ?? null) === 'unassigned', fn (Builder $query) => $query->whereNull('assigned_to'))
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $this->search($query, $term))
            // Visible applications are never drafts, so submitted_at is always
            // set (DB CHECK); plain column order lets the queue index serve it.
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? self::PER_PAGE))
            ->withQueryString()
            ->through(function (Application $application) use ($decide, $user, $referenceData): array {
                $decisions = $decide->options($application, $user);

                return [
                    'uuid' => $application->uuid,
                    'student_number' => $application->user->student_number,
                    'full_name_ar' => $application->full_name_ar,
                    'status' => $application->status->value,
                    'status_label' => $application->status->shownAs()->label(),
                    'phone' => $application->phone,
                    'decisions' => $decisions,
                    // What the message is filled with, only for those who send it.
                    'contact' => $decisions === [] ? null : [
                        'name' => $application->full_name_ar,
                        'number' => $application->user->student_number,
                        'branch' => $referenceData->name(ReferenceType::Branch, $application->branch_code),
                        'specialization' => $referenceData->name(ReferenceType::Category, $application->category_code),
                        'phone' => StudentIdentity::internationalPhone($application->phone),
                    ],
                ];
            });

        return Inertia::render('admin/applications/index', [
            'applications' => $applications,
            'templates' => $user->hasPermission(Permission::ApplicationsReview)
                ? MessageTemplate::query()->orderBy('id')->get(['id', 'title', 'status', 'body'])
                : [],
            'filters' => $filters,
            'periods' => ExamPeriod::query()->latest('opens_at')->get(['id', 'name_ar', 'name_en'])
                ->map(fn (ExamPeriod $period): array => ['id' => $period->id, 'name' => $period->localizedName()]),
            'statuses' => collect(ApplicationStatus::staffStatuses())
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
            'user:id,student_number,phone,created_at,closed_at',
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
            'decisionMessage' => $this->decisionMessage($application),
            'canViewDocuments' => $user->hasPermission(Permission::DocumentsView),
            'canAssign' => Gate::allows('assign', $application),
            'reviewers' => Gate::allows('assign', $application) ? $this->reviewers() : [],
            'student' => [
                'id' => $application->user->id,
                'student_number' => $application->user->student_number,
                'phone' => $application->user->phone,
                'created_at' => $application->user->created_at?->toIso8601String(),
                'closed_at' => $application->user->closed_at?->toIso8601String(),
            ],
            'canResetStudentAccess' => $user->hasPermission(Permission::UsersManage),
        ]);
    }

    /**
     * The message of the current decision, to send it again.
     *
     * @return array{text: string, phone: string|null}|null
     */
    private function decisionMessage(Application $application): ?array
    {
        if (! in_array($application->status, DecideApplication::DECISIONS, true)) {
            return null;
        }

        $event = $application->events->last(fn (ApplicationEvent $event): bool => $event->to_status === $application->status && filled($event->reason));

        return $event === null ? null : [
            'text' => (string) $event->reason,
            'phone' => StudentIdentity::internationalPhone($application->phone),
        ];
    }

    /**
     * Exact matches on the national ID or reference number, otherwise every
     * word of the term must start a word of the name (ignoring Arabic letter
     * variants and diacritics) or appear inside the reference number.
     *
     * @param  Builder<Application>  $query
     */
    private function search(Builder $query, string $term): void
    {
        $term = trim($term);
        $words = preg_split('/\s+/u', $this->foldArabic($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $name = 'full_name_ar';
        foreach (self::ARABIC_FOLDS as $from => $to) {
            $name = "REPLACE({$name}, '{$from}', '{$to}')";
        }

        $query->where(function (Builder $query) use ($term, $words, $name): void {
            $query->where('reference_number', Str::upper($term))
                ->orWhere('national_id_number', $term)
                ->orWhere(function (Builder $query) use ($words, $name): void {
                    foreach ($words as $word) {
                        $like = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word);

                        $query->where(fn (Builder $query) => $query
                            ->whereRaw("{$name} LIKE ? ESCAPE '!'", [$like.'%'])
                            ->orWhereRaw("{$name} LIKE ? ESCAPE '!'", ['% '.$like.'%'])
                            ->orWhereRaw("reference_number LIKE ? ESCAPE '!'", ['%'.Str::upper($like).'%']));
                    }
                });
        });
    }

    /**
     * A barcode reader types the student number from the form into the search
     * box and presses Enter: that goes straight to the student's application.
     */
    private function scannedApplication(?string $term, User $staff): ?Application
    {
        $number = StudentIdentity::normalizePhone($term);

        if ($number === null || preg_match('/^[0-9]{5,6}$/', $number) !== 1) {
            return null;
        }

        return Application::query()
            ->visibleToStaff($staff)
            ->whereRelation('user', 'student_number', (int) $number)
            ->latest('id')
            ->first();
    }

    /**
     * Strips Arabic diacritics and tatweel, then folds letter variants.
     */
    private function foldArabic(string $text): string
    {
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);

        return strtr($text, self::ARABIC_FOLDS);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function reviewers(): array
    {
        return array_values(User::query()
            ->staff()
            ->whereNull('deactivated_at')
            ->with(['roleAssignments', 'permissionOverrides'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->canBeAssignedApplications())
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->all());
    }
}
