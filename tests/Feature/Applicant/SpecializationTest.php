<?php

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Public\ApplicationTermsController;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\ExamPeriod;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * After registration the student picks a branch and specialization; the
 * choice is saved into their draft for the open period.
 */
beforeEach(function () {
    seedReferenceData();
});

test('registration leads to the branch and specialization page, then to the application form', function () {
    ExamPeriod::factory()->create();

    $this->withSession([ApplicationTermsController::SESSION_KEY => now()->toIso8601String()])
        ->post(route('register.store'), ['username' => 'new.student', 'phone' => '07701234567', 'terms' => '1'])
        ->assertRedirect(route('apply.specialization', absolute: false));

    $this->get(route('apply.specialization'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('apply/specialization')
        ->where('branches.0.code', 'IND')
        ->where('branches.0.name', 'الصناعي')
        ->has('branches.0.specializations', 6)
        ->where('branches.0.specializations.0.name', 'كهرباء')
        ->has('branches', 5));

    $response = $this->post(route('apply.specialization.store'), ['branch_code' => 'IND', 'category_code' => 'IND_ELC']);
    $application = Application::query()->sole();
    $response->assertRedirect(route('applications.edit', $application));

    $this->get(route('applications.edit', $application))->assertInertia(fn (Assert $page) => $page
        ->component('applicant/applications/edit')
        ->where('application.form.branch_code', 'IND')
        ->where('application.form.category_code', 'IND_ELC')
        ->missing('accessCode')
        ->missing('instructions'));

    // The access code waits for the first visit to the student card, once.
    $this->get(route('student.card'))->assertInertia(fn (Assert $page) => $page
        ->where('accessCode', fn ($code) => is_string($code) && $code !== '')
        ->etc());
    $this->get(route('student.card'))->assertInertia(fn (Assert $page) => $page->where('accessCode', null)->etc());
});

test('the choice is saved into the draft for the open period and audited', function () {
    $period = ExamPeriod::factory()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->post(route('apply.specialization.store'), ['branch_code' => 'COM', 'category_code' => 'COM_ACC'])
        ->assertRedirect();

    $application = Application::query()->where('user_id', $student->id)->sole();

    expect($application->exam_period_id)->toBe($period->id)
        ->and($application->status)->toBe(ApplicationStatus::Draft)
        ->and($application->branch_code)->toBe('COM')
        ->and($application->category_code)->toBe('COM_ACC')
        ->and(AuditEvent::query()->where('action', 'application.specialization_chosen')->where('subject_id', $application->id)->exists())->toBeTrue();
});

test('a specialization from another branch is rejected', function () {
    ExamPeriod::factory()->create();

    $this->actingAs(User::factory()->student()->create())
        ->post(route('apply.specialization.store'), ['branch_code' => 'IND', 'category_code' => 'COM_ACC'])
        ->assertSessionHasErrors(['category_code' => __('applications.errors.specialization_branch')]);

    expect(Application::query()->count())->toBe(0);
});

test('both lists are required', function () {
    ExamPeriod::factory()->create();

    $this->actingAs(User::factory()->student()->create())
        ->post(route('apply.specialization.store'), [])
        ->assertSessionHasErrors(['branch_code', 'category_code']);
});

test('the page shows the saved choice and is locked once the review has started', function () {
    $period = ExamPeriod::factory()->create();
    $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->create([
        'exam_period_id' => $period->id,
        'branch_code' => 'AGR',
        'category_code' => 'AGR_GEN',
    ]);

    $this->actingAs($application->user)->get(route('apply.specialization'))->assertInertia(fn (Assert $page) => $page
        ->where('selected.branch_code', 'AGR')
        ->where('selected.category_code', 'AGR_GEN')
        ->where('locked', true));
});

test('without an open period the page says so and Next goes to the card', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('apply.specialization'))
        ->assertInertia(fn (Assert $page) => $page->where('period', null)->where('branches', []));

    $this->post(route('apply.specialization.store'), ['branch_code' => 'IND', 'category_code' => 'IND_ELC'])
        ->assertRedirect(route('student.card'));
});

test('staff are sent away from this page', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('apply.specialization'))
        ->assertRedirect(route('dashboard'));
});

test('only branches eligible in the period are offered and accepted', function () {
    ExamPeriod::factory()->create(['eligible_branch_codes' => ['IND']]);
    $student = User::factory()->student()->create();

    $this->actingAs($student)->get(route('apply.specialization'))->assertInertia(fn (Assert $page) => $page
        ->has('branches', 1)
        ->where('branches.0.code', 'IND'));

    $this->post(route('apply.specialization.store'), ['branch_code' => 'COM', 'category_code' => 'COM_ACC'])
        ->assertSessionHasErrors('branch_code');
});
