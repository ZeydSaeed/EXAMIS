<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\AuditEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function listedApplication(ApplicationStatus $status = ApplicationStatus::Submitted): Application
{
    return Application::factory()
        ->for(User::factory()->student()->create(['student_number' => 647179]))
        ->inStatus($status)
        ->create();
}

function decide(Application $application, string $target, ?string $reason = null)
{
    return test()->post(route('admin.applications.decision', $application), ['target' => $target, 'reason' => $reason]);
}

it('lists the student number and four-part name, without assignee or date', function () {
    $application = listedApplication();

    $this->actingAs(staffWith(Role::Administrator))
        ->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('applications.data.0.uuid', $application->uuid)
            ->where('applications.data.0.student_number', 647179)
            ->where('applications.data.0.full_name_ar', $application->full_name_ar)
            ->missing('applications.data.0.assignee')
            ->missing('applications.data.0.submitted_at'));
});

it('shows the mobile number of each student', function () {
    $application = listedApplication();
    $application->forceFill(['phone' => '07701234567'])->save();

    $this->actingAs(staffWith(Role::Reviewer))
        ->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page->where('applications.data.0.phone', '07701234567'));
});

it('offers the three decisions to those who may take them', function () {
    listedApplication();

    $this->actingAs(staffWith(Role::Supervisor))
        ->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('applications.data.0.decisions.0.value', 'accepted')
            ->where('applications.data.0.decisions.0.requires_reason', false)
            ->where('applications.data.0.decisions.1.value', 'rejected')
            ->where('applications.data.0.decisions.1.requires_reason', true)
            ->where('applications.data.0.decisions.2.value', 'requires_correction')
            ->where('applications.data.0.decisions.2.requires_reason', true));
});

it('offers a reviewer only what a reviewer may do, and an auditor nothing', function () {
    listedApplication();

    $this->actingAs(staffWith(Role::Reviewer))
        ->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('applications.data.0.decisions', 1)
            ->where('applications.data.0.decisions.0.value', 'requires_correction'));

    $this->actingAs(staffWith(Role::Auditor))
        ->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page->has('applications.data.0.decisions', 0));
});

it('accepts a submitted application through the state machine', function () {
    $application = listedApplication();
    $supervisor = staffWith(Role::Supervisor);

    $this->actingAs($supervisor);

    decide($application, 'accepted')->assertSessionHasNoErrors();

    $events = ApplicationEvent::query()->where('application_id', $application->id)->orderBy('id')->get();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Accepted)
        ->and($events->pluck('to_status')->all())->toBe([ApplicationStatus::UnderReview, ApplicationStatus::Accepted])
        ->and($events->pluck('actor_id')->unique()->all())->toBe([$supervisor->id])
        ->and(AuditEvent::query()->where('action', 'application.accepted')->where('actor_id', $supervisor->id)->exists())->toBeTrue();
});

it('needs a reason to reject or to ask for what is missing', function (string $target) {
    $application = listedApplication();

    $this->actingAs(staffWith(Role::Supervisor));

    decide($application, $target)->assertSessionHasErrors('reason');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);

    decide($application, $target, 'الصورة غير واضحة')->assertSessionHasNoErrors();

    expect($application->fresh()->status->value)->toBe($target);
})->with(['rejected', 'requires_correction']);

it('changes an earlier decision only with a reason', function () {
    $application = listedApplication(ApplicationStatus::Accepted);

    $this->actingAs(staffWith(Role::Supervisor));

    decide($application, 'rejected')->assertSessionHasErrors('reason');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Accepted);

    decide($application, 'rejected', 'خطأ في التدقيق')->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe(ApplicationStatus::Rejected);
});

it('leaves applications waiting on the student or withdrawn alone', function (ApplicationStatus $status) {
    $application = listedApplication($status);

    $this->actingAs(staffWith(Role::Administrator));

    decide($application, 'accepted', 'سبب')->assertSessionHasErrors();

    expect($application->fresh()->status)->toBe($status);
})->with([ApplicationStatus::RequiresCorrection, ApplicationStatus::Withdrawn]);

it('refuses a decision without the permission, and any other target', function () {
    $application = listedApplication();

    $this->actingAs(staffWith(Role::Reviewer));
    decide($application, 'accepted')->assertForbidden();

    $this->actingAs(staffWith(Role::Supervisor));
    decide($application, 'withdrawn')->assertSessionHasErrors('target');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('undoes the first step when the decision fails', function () {
    $application = listedApplication();

    $this->actingAs(staffWith(Role::Reviewer));
    decide($application, 'accepted')->assertForbidden();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted)
        ->and(ApplicationEvent::query()->where('application_id', $application->id)->count())->toBe(0);
});

it('opens the student form from the list', function () {
    $application = listedApplication();

    $response = $this->actingAs(staffWith(Role::Reviewer))
        ->get(route('admin.applications.receipt', $application))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline');
});
