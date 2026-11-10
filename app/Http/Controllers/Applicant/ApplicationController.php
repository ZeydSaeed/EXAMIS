<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Applications\SaveApplicationDraft;
use App\Actions\Applications\StartApplication;
use App\Enums\ApplicationStatus;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\SaveApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\DocumentType;
use App\Models\ExamPeriod;
use App\Services\ReferenceData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    /**
     * Start (or resume) the applicant's application for an open period.
     */
    public function store(Request $request, StartApplication $startApplication): RedirectResponse
    {
        Gate::authorize('create', Application::class);

        $validated = $request->validate([
            'exam_period_id' => ['required', 'integer'],
        ]);

        $period = ExamPeriod::query()->whereKey((int) $validated['exam_period_id'])->firstOrFail();
        $application = $startApplication->handle($request->user(), $period);

        return to_route('applications.edit', $application);
    }

    public function show(Application $application): Response
    {
        Gate::authorize('view', $application);

        $application->load(['examPeriod.documentTypes', 'currentDocuments.documentType', 'events' => fn ($query) => $query->orderBy('id')]);

        return Inertia::render('applicant/applications/show', [
            'application' => ApplicationResource::make($application)->resolve(),
            'documentTypes' => $this->documentTypes($application),
            'missing' => $application->status->isEditableByApplicant() ? $application->missingRequirements() : null,
            'can' => [
                'edit' => $application->status->isEditableByApplicant(),
                'withdraw' => Gate::allows('withdraw', $application),
                'receipt' => $application->reference_number !== null,
            ],
        ]);
    }

    public function edit(Request $request, Application $application, ReferenceData $referenceData): Response|RedirectResponse
    {
        Gate::authorize('view', $application);

        if (! $application->status->isEditableByApplicant()) {
            return to_route('applications.show', $application);
        }

        $application->load(['examPeriod.documentTypes', 'currentDocuments.documentType']);

        return Inertia::render('applicant/applications/edit', [
            'application' => ApplicationResource::make($application)->resolve(),
            'documentTypes' => $this->documentTypes($application),
            'missing' => $application->missingRequirements(),
            'options' => [
                'governorates' => $referenceData->items(ReferenceType::Governorate),
            ],
            'step' => $this->step($request, $application),
        ]);
    }

    public function update(SaveApplicationRequest $request, Application $application, SaveApplicationDraft $saveDraft): RedirectResponse
    {
        $validated = $request->validated();
        $step = $validated['step'] ?? null;
        unset($validated['step']);

        $saveDraft->handle($application, $validated, (int) $validated['lock_version'], $request->user());

        return $step === null ? back() : to_route('applications.edit', [$application, 'step' => (int) $step + 1]);
    }

    /**
     * The step to open: never past the first incomplete one. A draft resumes
     * there and cannot return to a finished step until everything is
     * complete: the review may then reopen any step, and the student moves
     * forward from it again. Anything already submitted starts from the top
     * to be edited.
     */
    private function step(Request $request, Application $application): int
    {
        $furthest = $application->furthestStep();
        $requested = $request->integer('step');

        if ($application->status !== ApplicationStatus::Draft) {
            return $requested > 0 ? min($requested, $furthest) : 1;
        }

        if ($requested <= 0) {
            return $furthest;
        }

        if ($furthest === Application::STEP_REVIEW) {
            return min($requested, $furthest);
        }

        return $furthest;
    }

    /**
     * @return list<array{code: string, name: string, description: string|null, required: bool, mime_types: list<string>, max_size_kb: int, is_photo: bool}>
     */
    private function documentTypes(Application $application): array
    {
        return array_values($application->examPeriod->documentTypes
            ->sortBy('id')
            ->map(fn (DocumentType $type): array => [
                'code' => $type->code,
                'name' => $type->localizedName(),
                'description' => app()->getLocale() === 'en' ? $type->description_en : $type->description_ar,
                'required' => (bool) $type->pivot->is_required,
                'mime_types' => $type->acceptedMimeTypes(),
                'max_size_kb' => $type->max_size_kb,
                'is_photo' => $type->is_photo,
            ])
            ->all());
    }
}
