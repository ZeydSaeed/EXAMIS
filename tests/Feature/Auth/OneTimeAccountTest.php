<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Http\Controllers\Public\ApplicationTermsController;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * One-time student accounts (ADR-0010): the account ends when the student
 * confirms the form was printed or downloaded, and a student who comes back
 * starts again with a new id.
 */
beforeEach(function () {
    seedReferenceData();
});

function printedStudent(): array
{
    $student = User::factory()->student()->create();
    $student->trustedDevices()->create(['token_hash' => hash('sha256', 'device'), 'expires_at' => now()->addDays(30)]);
    $application = Application::factory()->for($student)->complete()->submitted()->create();

    return [$student, $application];
}

function finishPrinting(TestCase $test, User $student, Application $application): TestResponse
{
    return $test->actingAs($student)
        ->followingRedirects()
        ->post(route('applications.finish', $application));
}

test('sending the application keeps the account open on the final page', function () {
    $application = readyDraft();
    $student = $application->user;

    $this->actingAs($student)
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertRedirect(route('applications.printed', $application));

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->fresh()->reference_number)->not->toBeNull()
        ->and($student->fresh()->isActive())->toBeTrue();

    $this->get(route('applications.printed', $application))->assertInertia(fn (Assert $page) => $page
        ->component('applicant/applications/printed')
        ->where('studentNumber', $student->student_number)
        ->where('finishUrl', route('applications.finish', $application))
        ->missing('referenceNumber'));
});

test('opening or downloading the form closes nothing, however often', function () {
    [$student, $application] = printedStudent();

    $this->actingAs($student);
    $this->get(route('applications.receipt', $application))->assertOk();
    $this->get(route('applications.receipt', [$application, 'download' => 1]))->assertOk();

    expect($student->fresh()->isActive())->toBeTrue()
        ->and($student->fresh()->access_code)->not->toBeNull();
    $this->assertAuthenticatedAs($student);
});

test('the account stays open however long the student takes', function () {
    [$student, $application] = printedStudent();

    $this->actingAs($student)->get(route('applications.printed', $application))->assertOk();
    $this->travel(30)->days();

    $this->get(route('applications.printed', $application))->assertOk();
    expect($student->fresh()->isActive())->toBeTrue();
});

test('finishing the print closes the account, removes every way back in and signs out', function () {
    [$student, $application] = printedStudent();

    $this->actingAs($student)
        ->post(route('applications.finish', $application))
        ->assertRedirect(route('applications.printed', $application));

    $this->get(route('applications.printed', $application))
        ->assertRedirect(route('apply.terms'))
        ->assertSessionHas('status', __('auth.account_closed'));
    $this->assertGuest();

    $student->refresh();
    expect($student->closed_at)->not->toBeNull()
        ->and($student->isActive())->toBeFalse()
        ->and($student->access_code)->toBeNull()
        ->and($student->trustedDevices()->usable()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'student.account_closed')->where('subject_id', $student->id)->exists())->toBeTrue();
});

test('the form cannot be reached once the account has closed', function () {
    [$student, $application] = printedStudent();
    finishPrinting($this, $student, $application);

    $this->assertGuest();
    $this->get(route('applications.receipt', $application))->assertRedirect(route('login'));
});

test('a repeated finish goes the same way', function () {
    [$student, $application] = printedStudent();
    $this->actingAs($student)->post(route('applications.finish', $application));

    $this->post(route('applications.finish', $application))->assertRedirect(route('apply.terms'));

    $this->assertGuest();
    expect(AuditEvent::query()->where('action', 'student.account_closed')->where('subject_id', $student->id)->count())->toBe(1);
});

test('the form opens in the browser or saves as a file', function () {
    [$student, $application] = printedStudent();

    $this->actingAs($student);

    expect($this->get(route('applications.receipt', $application))->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($this->get(route('applications.receipt', [$application, 'download' => 1]))->headers->get('Content-Disposition'))->toStartWith('attachment');
});

test('the final page is only for a sent application waiting to be printed', function () {
    $student = User::factory()->student()->create();
    $draft = Application::factory()->for($student)->create();

    $this->actingAs($student)->get(route('applications.printed', $draft))->assertForbidden();

    $draft->forceFill(['reference_number' => 'EX2027-123456784', 'status' => ApplicationStatus::RequiresCorrection])->save();

    $this->get(route('applications.printed', $draft))->assertRedirect(route('applications.show', $draft));
});

test('until the print is finished nothing else can be reached, not even sign-out', function () {
    [$student, $application] = printedStudent();
    $this->actingAs($student);
    $finalPage = route('applications.printed', $application);

    $this->get(route('dashboard'))->assertRedirect($finalPage);
    $this->get(route('applications.edit', $application))->assertRedirect($finalPage);
    $this->get(route('student.card'))->assertRedirect($finalPage);
    $this->post(route('logout'))->assertRedirect($finalPage);

    $this->assertAuthenticatedAs($student);
});

test('a closed account cannot sign in with its number, QR link or a remembered device', function () {
    [$student, $application] = printedStudent();
    finishPrinting($this, $student, $application);

    $this->post(route('student.login'), ['identifier' => (string) $student->student_number])
        ->assertRedirect(route('student.code', absolute: false));
    $this->post(route('student.verify'), ['access_code' => 'ABCDE-23456'])->assertSessionHasErrors('access_code');
    $this->assertGuest();

    $this->get(route('student.qr', $student->public_id))->assertRedirect(route('student.code', absolute: false));
    $this->assertGuest();
});

test('an open account that leaves before printing comes back to the final page', function () {
    [$student, $application] = printedStudent();

    auth()->guard('web')->logout();

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertRedirect(route('applications.printed', $application));
});

test('an application waiting for a correction keeps the account open and free to move', function () {
    [$student, $application] = printedStudent();
    $application->forceFill(['status' => ApplicationStatus::RequiresCorrection])->save();

    $this->actingAs($student)->get(route('applications.receipt', $application))->assertOk();
    $this->get(route('applications.show', $application))->assertOk();

    $this->post(route('applications.finish', $application))
        ->assertRedirect(route('applications.printed', $application));

    expect($student->fresh()->isActive())->toBeTrue();
});

test('nobody else can finish the print for a student', function () {
    [$student, $application] = printedStudent();

    $this->actingAs(User::factory()->student()->create())
        ->post(route('applications.finish', $application))
        ->assertNotFound();

    $staff = User::factory()->staff(Role::Administrator)->create();
    $this->actingAs($staff)->post(route('applications.finish', $application))->assertNotFound();

    expect($student->fresh()->isActive())->toBeTrue()
        ->and($staff->fresh()->isActive())->toBeTrue();
});

test('staff printing a form do not close the student account', function () {
    [$student, $application] = printedStudent();
    $staff = User::factory()->staff(Role::Administrator)->create();

    $this->actingAs($staff)->get(route('applications.receipt', $application))->assertOk();

    expect($student->fresh()->isActive())->toBeTrue();
});

test('staff cannot issue a new access code for a closed account', function () {
    [$student, $application] = printedStudent();
    finishPrinting($this, $student, $application);

    $this->actingAs(User::factory()->staff(Role::Administrator)->create())
        ->post(route('admin.students.access-code', $student))
        ->assertForbidden();
});

test('a student who comes back registers again on the same number with a new id', function () {
    [$student, $application] = printedStudent();
    finishPrinting($this, $student, $application);

    $this->withSession([ApplicationTermsController::SESSION_KEY => now()->toIso8601String()])
        ->post(route('register.store'), ['phone' => $student->phone])
        ->assertSessionHasNoErrors();

    $second = User::query()->where('phone', $student->phone)->whereKeyNot($student->id)->sole();

    expect($second->student_number)->not->toBe($student->student_number)
        ->and($second->public_id)->not->toBe($student->public_id)
        ->and($second->isActive())->toBeTrue()
        ->and(auth()->id())->toBe($second->id);
});
