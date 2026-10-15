<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationEventType;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\ExamPeriod;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Opens (or returns the existing) draft for an applicant in an exam period.
 * Idempotent: double clicks and concurrent requests resolve to the same row
 * because of the unique (user_id, exam_period_id) constraint (ADR-0003).
 */
class StartApplication
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $applicant, ExamPeriod $period): Application
    {
        $existing = $this->findExisting($applicant, $period);

        if ($existing !== null) {
            return $existing;
        }

        if (! $period->isAcceptingApplications()) {
            throw DomainRuleViolation::periodClosed();
        }

        try {
            return DB::transaction(function () use ($applicant, $period): Application {
                $application = new Application($this->prefillFromLatest($applicant));
                $application->user_id = $applicant->id;
                $application->exam_period_id = $period->id;
                $application->contact_email ??= $applicant->email;
                $application->save();

                $application->events()->create([
                    'actor_id' => $applicant->id,
                    'type' => ApplicationEventType::Created,
                ]);

                $this->audit->record('application.created', $application, actor: $applicant);

                return $application;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->findExisting($applicant, $period)
                ?? throw new \LogicException('Application vanished after a unique violation.');
        }
    }

    private function findExisting(User $applicant, ExamPeriod $period): ?Application
    {
        return Application::query()
            ->where('user_id', $applicant->id)
            ->where('exam_period_id', $period->id)
            ->first();
    }

    /**
     * Personal details from the applicant's most recent application save
     * re-typing; study choices are period specific and are not copied.
     *
     * @return array<string, mixed>
     */
    private function prefillFromLatest(User $applicant): array
    {
        $latest = Application::query()
            ->where('user_id', $applicant->id)
            ->latest('id')
            ->first();

        if ($latest === null) {
            return [];
        }

        return $latest->only([
            'full_name_ar', 'surname', 'mother_name_ar', 'birth_date', 'gender', 'national_id_number',
            'phone', 'district', 'mahalla', 'alley', 'house_number', 'school_name', 'intermediate_school_name',
        ]);
    }
}
