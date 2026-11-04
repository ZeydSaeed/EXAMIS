<?php

use App\Enums\ApplicationStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Application;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The form is one step at a time with no way back, so "save and continue"
 * must not let a required field through: nothing could fix it from the
 * review step.
 */
beforeEach(function () {
    seedReferenceData();
});

function draftFor(User $student, array $attributes = []): Application
{
    return Application::factory()->for($student)->create($attributes);
}

function personalStep(): array
{
    return [
        'student_name' => 'علي', 'father_name' => 'حسن', 'grandfather_name' => 'كاظم', 'great_grandfather_name' => 'جواد',
        'mother_first_name' => 'زينب', 'mother_father_name' => 'مهدي', 'mother_grandfather_name' => 'كريم', 'birth_date' => '2000-01-01',
        'birth_place_code' => 'KRB', 'gender' => 'male',
    ];
}

test('save and continue refuses a step with required fields left empty', function () {
    $student = User::factory()->student()->create();
    $application = draftFor($student, ['student_name' => null]);

    $this->actingAs($student)->put(route('applications.update', $application), [
        'lock_version' => $application->lock_version,
        'step' => 1,
    ])->assertSessionHasErrors(['student_name', 'gender']);
});

test('save and continue moves to the next step', function () {
    $student = User::factory()->student()->create();
    $application = draftFor($student);

    $this->actingAs($student)->put(route('applications.update', $application), [
        ...personalStep(),
        'lock_version' => $application->lock_version,
        'step' => 1,
    ])->assertRedirect(route('applications.edit', [$application, 'step' => 2]));
});

test('a place of birth outside the list must be written before continuing', function () {
    $student = User::factory()->student()->create();
    $application = draftFor($student);

    $this->actingAs($student)->put(route('applications.update', $application), [
        ...personalStep(),
        'birth_place_code' => Application::BIRTH_PLACE_OTHER,
        'lock_version' => $application->lock_version,
        'step' => 1,
    ])->assertSessionHasErrors('birth_place_other');
});

test('a draft cannot skip ahead of its first incomplete step', function () {
    $student = User::factory()->student()->create();
    $application = draftFor($student, ['student_name' => null]);

    $this->actingAs($student)
        ->get(route('applications.edit', [$application, 'step' => 5]))
        ->assertInertia(fn (Assert $page) => $page->where('step', 1));
});

test('a returning draft resumes at its first incomplete step', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->complete()->create(['district' => null]);

    $this->actingAs($student)
        ->get(route('applications.edit', $application))
        ->assertInertia(fn (Assert $page) => $page->where('step', 2));
});

test('a complete draft without its documents stops at the documents step', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->complete()->create();
    $application->load('examPeriod.documentTypes', 'currentDocuments');

    expect($application->furthestStep())->toBe($application->missingRequirements()['documents'] === [] ? 5 : 4);
});

test('the national id number is no longer required', function () {
    $application = Application::factory()->complete()->create(['national_id_number' => null]);

    expect($application->missingFields())->not->toContain('national_id_number');
});

test('the mahalla, alley and house number can stay empty', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->complete()->create(['mahalla' => null, 'alley' => null, 'house_number' => null]);

    expect($application->missingFields())->toBe([]);

    $this->actingAs($student)->put(route('applications.update', $application), [
        'lock_version' => $application->lock_version,
        'district' => 'الحسين',
        'step' => 2,
    ])->assertSessionHasNoErrors();

    expect($application->fresh()->only(['mahalla', 'alley', 'house_number']))
        ->toBe(['mahalla' => null, 'alley' => null, 'house_number' => null]);
});

test('the last school is required', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->complete()->create(['school_name' => null]);

    expect($application->missingFields())->toBe(['school_name']);

    $this->actingAs($student)->put(route('applications.update', $application), [
        'lock_version' => $application->lock_version,
        'step' => 3,
    ])->assertSessionHasErrors('school_name');
});

test('a governorate outside the list must be written before continuing', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->complete()->create();
    $save = fn (array $data) => $this->actingAs($student)->put(route('applications.update', $application), [
        'lock_version' => $application->fresh()->lock_version,
        'school_name' => 'إعدادية النجاح للبنين',
        'school_governorate_code' => Application::SCHOOL_GOVERNORATE_OTHER,
        'step' => 3,
        ...$data,
    ]);

    $save([])->assertSessionHasErrors('school_governorate_other');

    $save(['school_governorate_other' => '  طهران  '])->assertSessionHasNoErrors();

    expect($application->fresh()->schoolGovernorate())->toBe('طهران');
});

test('choosing a listed governorate drops the written one', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->complete()->create([
        'school_governorate_code' => Application::SCHOOL_GOVERNORATE_OTHER,
        'school_governorate_other' => 'طهران',
    ]);

    $this->actingAs($student)->put(route('applications.update', $application), [
        'lock_version' => $application->lock_version,
        'school_governorate_code' => 'KRB',
        'school_governorate_other' => 'طهران',
    ])->assertSessionHasNoErrors();

    expect($application->fresh()->school_governorate_other)->toBeNull();
});

test('a draft cannot return to a finished step', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->complete()->create(['district' => null]);

    $this->actingAs($student)
        ->get(route('applications.edit', [$application, 'step' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('step', 2));
});

test('the review can reopen any step of a complete draft', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->complete()->create();

    expect($application->fresh()->furthestStep())->toBe(Application::STEP_REVIEW);

    $this->actingAs($student)
        ->get(route('applications.edit', [$application, 'step' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('step', 1));
});

test('the specialization page is closed once the form has started', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->complete()->create(['district' => null]);

    $this->actingAs($student)
        ->get(route('apply.specialization'))
        ->assertRedirect(route('applications.edit', $application));
});

test('a submitted application no longer opens the form', function () {
    $this->withoutMiddleware(EnsureAccountIsActive::class);

    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->complete()->submitted()->create();

    expect($application->status)->toBe(ApplicationStatus::Submitted);

    $this->actingAs($student)
        ->get(route('applications.edit', $application))
        ->assertRedirect(route('applications.show', $application));
});
