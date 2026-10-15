<?php

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Notifications\ApplicationStatusChanged;
use App\Services\Applications\ReferenceNumberGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;

it('submits a complete application with a valid reference number', function () {
    Notification::fake();
    $application = readyDraft();

    $this->actingAs($application->user)
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertRedirect(route('applications.show', $application));

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Submitted)
        ->and(ReferenceNumberGenerator::isWellFormed((string) $application->reference_number))->toBeTrue()
        ->and($application->reference_number)->toStartWith($application->examPeriod->code.'-')
        ->and($application->submitted_at)->not->toBeNull()
        ->and($application->declaration_accepted_at)->not->toBeNull();

    Notification::assertSentTo($application->user, ApplicationStatusChanged::class);
});

it('is idempotent when the submission is repeated', function () {
    Notification::fake();
    $application = readyDraft();

    $this->actingAs($application->user)->post(route('applications.submit', $application), ['declaration' => '1']);
    $reference = $application->fresh()->reference_number;

    $this->actingAs($application->user)
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertSessionHasNoErrors();

    $application->refresh();
    expect($application->reference_number)->toBe($reference)
        ->and($application->events()->where('type', ApplicationEventType::StatusChanged)->count())->toBe(1)
        ->and($application->examPeriod->fresh()->last_sequence)->toBe(1);
    Notification::assertSentToTimes($application->user, ApplicationStatusChanged::class, 1);
});

it('issues distinct sequential reference numbers within a period', function () {
    $first = readyDraft();
    $second = Application::factory()->complete()->create(['exam_period_id' => $first->exam_period_id]);
    ApplicationDocument::factory()->verified()->create([
        'application_id' => $second->id,
        'document_type_id' => $first->examPeriod->documentTypes()->value('document_types.id'),
    ]);

    $this->actingAs($first->user)->post(route('applications.submit', $first), ['declaration' => '1']);
    $this->actingAs($second->user)->post(route('applications.submit', $second), ['declaration' => '1']);

    $references = [$first->fresh()->reference_number, $second->fresh()->reference_number];
    expect($references[0])->not->toBe($references[1])
        ->and(substr((string) $references[0], -7, 6))->toBe('000001')
        ->and(substr((string) $references[1], -7, 6))->toBe('000002');
});

it('requires the declaration', function () {
    $application = readyDraft();

    $this->actingAs($application->user)
        ->post(route('applications.submit', $application))
        ->assertSessionHasErrors('declaration');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
});

it('lists what is missing when the application is incomplete', function () {
    [$period] = openPeriodRequiringDocument();
    $application = Application::factory()->create(['exam_period_id' => $period->id, 'full_name_ar' => 'اسم فقط']);

    $this->actingAs($application->user)
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertSessionHasErrors(['application' => __('applications.errors.incomplete')])
        ->assertSessionHas('domain_context.missing.documents', ['national_id']);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
});

it('does not accept a rejected document as the required one', function () {
    $application = readyDraft();
    $application->documents()->update(['status' => 'rejected']);

    $this->actingAs($application->user)
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertSessionHasErrors('application');
});

it('refuses new submissions after the period closes', function () {
    $application = readyDraft();
    $application->examPeriod->update(['closes_at' => now()->subMinute()]);

    $this->actingAs($application->user)
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertSessionHasErrors(['exam_period' => __('applications.errors.period_closed')]);
});

it('re-checks eligibility against the period rules at submission time', function () {
    $application = readyDraft();
    $application->examPeriod->update(['eligible_branch_codes' => ['COM']]);

    $this->actingAs($application->user)
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertSessionHasErrors('branch_code');
});

it('resubmits a corrected application without changing its reference number', function () {
    Notification::fake();
    $application = readyDraft();
    $this->actingAs($application->user)->post(route('applications.submit', $application), ['declaration' => '1']);
    $reference = $application->fresh()->reference_number;
    $application->forceFill(['status' => ApplicationStatus::RequiresCorrection])->save();
    $application->examPeriod->update(['closes_at' => now()->subDay()]);

    $this->actingAs($application->user)
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertSessionHasNoErrors();

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Resubmitted)
        ->and($application->reference_number)->toBe($reference);
});

it('answers 404 when submitting someone else\'s application', function () {
    $application = readyDraft();

    $this->actingAs(applicant())
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertNotFound();
});

it('lets the applicant withdraw a submitted application', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs($application->user)
        ->post(route('applications.withdraw', $application), ['confirm' => '1'])
        ->assertRedirect(route('applications.show', $application));

    expect($application->fresh()->status)->toBe(ApplicationStatus::Withdrawn)
        ->and($application->fresh()->withdrawn_at)->not->toBeNull();
});

it('does not allow withdrawing a decided application', function () {
    $application = Application::factory()->inStatus(ApplicationStatus::Accepted)->create();

    $this->actingAs($application->user)
        ->post(route('applications.withdraw', $application), ['confirm' => '1'])
        ->assertForbidden();
});

it('enforces one application per applicant per period in the database', function () {
    $application = Application::factory()->create();

    expect(fn () => Application::factory()->create([
        'user_id' => $application->user_id,
        'exam_period_id' => $application->exam_period_id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('allows only one current document per type in the database', function () {
    $document = ApplicationDocument::factory()->create();

    expect(fn () => ApplicationDocument::factory()->create([
        'application_id' => $document->application_id,
        'document_type_id' => $document->document_type_id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});
