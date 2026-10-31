<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Http\Controllers\Public\ApplicationTermsController;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * One-time student accounts (ADR-0010): opening the printable form ends the
 * account, and a student who comes back starts again with a new id.
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

test('opening the form closes the account and removes every way back in', function () {
    [$student, $application] = printedStudent();

    $this->actingAs($student)->get(route('applications.receipt', $application))->assertOk();

    $student->refresh();
    expect($student->closed_at)->not->toBeNull()
        ->and($student->isActive())->toBeFalse()
        ->and($student->access_code)->toBeNull()
        ->and($student->trustedDevices()->usable()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'student.account_closed')->where('subject_id', $student->id)->exists())->toBeTrue();
});

test('printing the form sends the application and closes the account', function () {
    $application = readyDraft();
    $student = $application->user;

    $this->actingAs($student)
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertRedirect(route('applications.printed', $application));

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->fresh()->reference_number)->not->toBeNull()
        ->and($student->fresh()->isClosed())->toBeTrue()
        ->and($student->fresh()->access_code)->toBeNull();

    $this->get(route('applications.printed', $application))->assertInertia(fn (Assert $page) => $page
        ->component('applicant/applications/printed')
        ->where('studentNumber', $student->student_number)
        ->missing('referenceNumber'));
    $this->get(route('applications.receipt', $application))->assertOk();
});

test('the form opens in the browser or saves as a file', function () {
    [$student, $application] = printedStudent();

    $this->actingAs($student);

    expect($this->get(route('applications.receipt', $application))->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($this->get(route('applications.receipt', [$application, 'download' => 1]))->headers->get('Content-Disposition'))->toStartWith('attachment');
});

test('the final page is only for a closed account', function () {
    [$student, $application] = printedStudent();

    $this->actingAs($student)
        ->get(route('applications.printed', $application))
        ->assertRedirect(route('applications.show', $application));
});

test('after printing nothing but the final page can be reached, not even sign-out', function () {
    [$student, $application] = printedStudent();
    $this->actingAs($student)->get(route('applications.receipt', $application));
    $finalPage = route('applications.printed', $application);

    $this->get(route('dashboard'))->assertRedirect($finalPage);
    $this->get(route('applications.edit', $application))->assertRedirect($finalPage);
    $this->get(route('student.card'))->assertRedirect($finalPage);
    $this->post(route('logout'))->assertRedirect($finalPage);

    $this->assertAuthenticatedAs($student);
});

test('a closed account cannot sign in with its number, QR link or a remembered device', function () {
    [$student, $application] = printedStudent();
    $this->actingAs($student)->get(route('applications.receipt', $application));
    auth()->guard('web')->logout();
    $this->flushSession();

    $this->post(route('student.login'), ['identifier' => (string) $student->student_number])
        ->assertRedirect(route('student.code', absolute: false));
    $this->post(route('student.verify'), ['access_code' => 'ABCDE-23456'])->assertSessionHasErrors('access_code');
    $this->assertGuest();

    $this->get(route('student.qr', $student->public_id))->assertRedirect(route('student.code', absolute: false));
    $this->assertGuest();
});

test('the printing session can reprint within the window, then ends', function () {
    [$student, $application] = printedStudent();

    $this->actingAs($student)->get(route('applications.receipt', $application))->assertOk();
    $this->get(route('applications.receipt', $application))->assertOk();

    $this->travel((int) config('examis.students.print_window_minutes') + 1)->minutes();

    $this->get(route('dashboard'))
        ->assertRedirect(route('apply.terms'))
        ->assertSessionHas('status', __('auth.account_closed'));
    $this->assertGuest();
});

test('a window of zero minutes ends the session on the next request', function () {
    config(['examis.students.print_window_minutes' => 0]);
    [$student, $application] = printedStudent();

    $this->actingAs($student)->get(route('applications.receipt', $application))->assertOk();

    $this->get(route('dashboard'))->assertRedirect(route('apply.terms'));
    $this->assertGuest();
});

test('an application waiting for a correction keeps the account open when printed', function () {
    [$student, $application] = printedStudent();
    $application->forceFill(['status' => ApplicationStatus::RequiresCorrection])->save();

    $this->actingAs($student)->get(route('applications.receipt', $application))->assertOk();

    expect($student->fresh()->isActive())->toBeTrue();
});

test('staff printing a form do not close the student account', function () {
    [$student, $application] = printedStudent();
    $staff = User::factory()->staff(Role::Administrator)->create();

    $this->actingAs($staff)->get(route('applications.receipt', $application))->assertOk();

    expect($student->fresh()->isActive())->toBeTrue();
});

test('staff cannot issue a new access code for a closed account', function () {
    [$student, $application] = printedStudent();
    $this->actingAs($student)->get(route('applications.receipt', $application));

    $this->actingAs(User::factory()->staff(Role::Administrator)->create())
        ->post(route('admin.students.access-code', $student))
        ->assertForbidden();
});

test('a student who comes back registers again on the same number with a new id', function () {
    [$student, $application] = printedStudent();
    $this->actingAs($student)->get(route('applications.receipt', $application));
    auth()->guard('web')->logout();
    $this->flushSession();

    $this->withSession([ApplicationTermsController::SESSION_KEY => now()->toIso8601String()])
        ->post(route('register.store'), ['phone' => $student->phone])
        ->assertSessionHasNoErrors();

    $second = User::query()->where('phone', $student->phone)->whereKeyNot($student->id)->sole();

    expect($second->student_number)->not->toBe($student->student_number)
        ->and($second->public_id)->not->toBe($student->public_id)
        ->and($second->isActive())->toBeTrue()
        ->and(auth()->id())->toBe($second->id);
});
