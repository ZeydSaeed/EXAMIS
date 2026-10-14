<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The visibility and decision matrix for every role, checked at the policy
 * so a failure names the rule that is wrong.
 */
function canView(User $user, Application $application): bool
{
    return Gate::forUser($user)->inspect('view', $application)->allowed();
}

test('owners always see their own application', function (ApplicationStatus $status) {
    $application = Application::factory()->inStatus($status)->create();

    expect(canView($application->user, $application))->toBeTrue();
})->with(ApplicationStatus::cases());

test('other applicants never see an application and get a 404', function () {
    $application = Application::factory()->submitted()->create();
    $response = Gate::forUser(applicant())->inspect('view', $application);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});

test('no staff role sees drafts', function (Role $role) {
    $draft = Application::factory()->complete()->create();

    expect(canView(staffWith($role), $draft))->toBeFalse();
})->with(Role::cases());

test('staff visibility of submitted applications by role', function (Role $role, bool $visible) {
    $application = Application::factory()->submitted()->create();

    expect(canView(staffWith($role), $application))->toBe($visible);
})->with([
    [Role::Administrator, true],
    [Role::Supervisor, true],
    [Role::Reviewer, true],
    [Role::Auditor, true],
]);

test('reviewers do not see applications under review by a colleague', function () {
    $colleague = staffWith(Role::Reviewer);
    $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->assignedTo($colleague)->create();

    expect(canView(staffWith(Role::Reviewer), $application))->toBeFalse()
        ->and(canView($colleague, $application))->toBeTrue()
        ->and(canView(staffWith(Role::Supervisor), $application))->toBeTrue();
});

test('the visibility scope agrees with the policy', function () {
    $reviewer = staffWith(Role::Reviewer);
    Application::factory()->complete()->create();
    Application::factory()->submitted()->create();
    Application::factory()->inStatus(ApplicationStatus::UnderReview)->assignedTo($reviewer)->create();
    Application::factory()->inStatus(ApplicationStatus::UnderReview)->assignedTo(staffWith(Role::Reviewer))->create();

    $scoped = Application::query()->visibleToStaff($reviewer)->pluck('id')->sort()->values()->all();
    $byPolicy = Application::query()->get()->filter(fn (Application $a) => $a->isVisibleToStaff($reviewer))->pluck('id')->sort()->values()->all();

    expect($scoped)->toBe($byPolicy)->and($scoped)->toHaveCount(2);
});

test('decision permissions by role', function (Role $role, ApplicationStatus $target, bool $allowed) {
    $staff = staffWith($role);
    $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->assignedTo($staff)->create();

    expect(Gate::forUser($staff)->allows('transition', [$application, $target]))->toBe($allowed);
})->with([
    [Role::Reviewer, ApplicationStatus::RequiresCorrection, true],
    [Role::Reviewer, ApplicationStatus::Accepted, false],
    [Role::Supervisor, ApplicationStatus::Accepted, true],
    [Role::Supervisor, ApplicationStatus::Rejected, true],
    [Role::Administrator, ApplicationStatus::Accepted, false],
    [Role::Administrator, ApplicationStatus::RequiresCorrection, false],
    [Role::Auditor, ApplicationStatus::RequiresCorrection, false],
]);

test('deactivated staff lose every permission', function () {
    $supervisor = staffWith(Role::Supervisor);
    $supervisor->forceFill(['deactivated_at' => now()])->save();

    expect(canView($supervisor->fresh(), Application::factory()->submitted()->create()))->toBeFalse();
});
