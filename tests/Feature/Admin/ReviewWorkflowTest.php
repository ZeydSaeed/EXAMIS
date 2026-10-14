<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Notifications\ApplicationStatusChanged;
use Illuminate\Support\Facades\Notification;

it('lets a reviewer start reviewing and assigns the application to them', function () {
    $application = Application::factory()->submitted()->create();
    $reviewer = staffWith(Role::Reviewer);

    $this->actingAs($reviewer)
        ->post(route('admin.applications.transition', $application), ['target' => 'under_review'])
        ->assertRedirect(route('admin.applications.show', $application));

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::UnderReview)
        ->and($application->assigned_to)->toBe($reviewer->id);
});

it('requires a reason to request a correction and notifies the applicant', function () {
    Notification::fake();
    $reviewer = staffWith(Role::Reviewer);
    $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->assignedTo($reviewer)->create();

    $this->actingAs($reviewer)
        ->post(route('admin.applications.transition', $application), ['target' => 'requires_correction'])
        ->assertSessionHasErrors(['reason' => __('applications.errors.reason_required')]);

    $this->actingAs($reviewer)
        ->post(route('admin.applications.transition', $application), ['target' => 'requires_correction', 'reason' => 'صورة البطاقة غير واضحة.'])
        ->assertSessionHasNoErrors();

    expect($application->fresh()->status)->toBe(ApplicationStatus::RequiresCorrection)
        ->and($application->events()->latest('id')->first()->reason)->toBe('صورة البطاقة غير واضحة.');
    Notification::assertSentTo($application->user, ApplicationStatusChanged::class, fn ($n) => $n->reason === 'صورة البطاقة غير واضحة.');
});

it('does not let a reviewer make the final decision', function () {
    $reviewer = staffWith(Role::Reviewer);
    $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->assignedTo($reviewer)->create();

    $this->actingAs($reviewer)
        ->post(route('admin.applications.transition', $application), ['target' => 'accepted'])
        ->assertForbidden();

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderReview);
});

it('lets a supervisor accept an application under review', function () {
    $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->create();

    $this->actingAs(staffWith(Role::Supervisor))
        ->post(route('admin.applications.transition', $application), ['target' => 'accepted'])
        ->assertSessionHasNoErrors();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Accepted)
        ->and($application->fresh()->decided_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'application.accepted')->exists())->toBeTrue();
});

it('rejects transitions the state machine does not allow', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs(staffWith(Role::Supervisor))
        ->post(route('admin.applications.transition', $application), ['target' => 'accepted'])
        ->assertSessionHasErrors(['status' => __('applications.errors.invalid_transition')]);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('only lets supervisors reopen a decision, with a reason, keeping the history', function () {
    $application = Application::factory()->inStatus(ApplicationStatus::Rejected)->create();
    $application->events()->create(['type' => 'status_changed', 'from_status' => 'under_review', 'to_status' => 'rejected', 'reason' => 'نقص المستمسكات']);

    $this->actingAs(staffWith(Role::Reviewer))
        ->post(route('admin.applications.transition', $application), ['target' => 'under_review', 'reason' => 'خطأ'])
        ->assertNotFound();

    $this->actingAs(staffWith(Role::Supervisor))
        ->post(route('admin.applications.transition', $application), ['target' => 'under_review'])
        ->assertSessionHasErrors('reason');

    $this->actingAs(staffWith(Role::Supervisor))
        ->post(route('admin.applications.transition', $application), ['target' => 'under_review', 'reason' => 'قرار رُفض بالخطأ'])
        ->assertSessionHasNoErrors();

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderReview)
        ->and($application->events()->where('to_status', 'rejected')->exists())->toBeTrue();
});

it('hides applications assigned to another reviewer', function () {
    $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->assignedTo(staffWith(Role::Reviewer))->create();

    $this->actingAs(staffWith(Role::Reviewer))
        ->post(route('admin.applications.transition', $application), ['target' => 'requires_correction', 'reason' => 'x'])
        ->assertNotFound();
});

it('treats a repeated identical decision as a no-op', function () {
    $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->create();
    $supervisor = staffWith(Role::Supervisor);

    $this->actingAs($supervisor)->post(route('admin.applications.transition', $application), ['target' => 'accepted']);
    $this->actingAs($supervisor)->post(route('admin.applications.transition', $application), ['target' => 'accepted'])->assertSessionHasNoErrors();

    expect($application->events()->where('to_status', 'accepted')->count())->toBe(1);
});

it('lets supervisors assign only staff who can review', function () {
    $application = Application::factory()->submitted()->create();
    $supervisor = staffWith(Role::Supervisor);
    $reviewer = staffWith(Role::Reviewer);
    $auditor = staffWith(Role::Auditor);

    $this->actingAs($supervisor)
        ->post(route('admin.applications.assign', $application), ['assignee_id' => $auditor->id])
        ->assertSessionHasErrors('assignee');

    $this->actingAs($supervisor)
        ->post(route('admin.applications.assign', $application), ['assignee_id' => $reviewer->id])
        ->assertSessionHasNoErrors();

    expect($application->fresh()->assigned_to)->toBe($reviewer->id);
});

it('does not let reviewers reassign work', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs(staffWith(Role::Reviewer))
        ->post(route('admin.applications.assign', $application), ['assignee_id' => null])
        ->assertForbidden();
});

it('keeps applicants out of the administration area', function () {
    $this->actingAs(applicant())->get(route('admin.dashboard'))->assertForbidden();
});

it('requires two-factor authentication for staff when configured', function () {
    config(['examis.security.require_staff_two_factor' => true]);

    $this->actingAs(staffWith(Role::Supervisor))
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('security.edit'));
});

it('restricts configuration sections to their permission', function (Role $role, string $route, int $status) {
    config(['examis.security.require_staff_two_factor' => false]);

    $this->actingAs(staffWith($role))->get(route($route))->assertStatus($status);
})->with([
    'reviewer cannot manage periods' => [Role::Reviewer, 'admin.periods.index', 403],
    'reviewer cannot manage users' => [Role::Reviewer, 'admin.users.index', 403],
    'supervisor cannot read the audit log' => [Role::Supervisor, 'admin.audit.index', 403],
    'auditor cannot manage content' => [Role::Auditor, 'admin.content.index', 403],
    'administrator can open reports' => [Role::Administrator, 'admin.reports.index', 200],
]);
