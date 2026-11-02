<?php

use App\Enums\OverrideEffect;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\User;

/**
 * The reviewed grant of every role, written out by hand. Adding a permission
 * or a grant must change this file, so nothing sensitive arrives unreviewed.
 */
function reviewedMatrix(): array
{
    return [
        'administrator' => ['admin.access', 'applications.view', 'applications.view_all', 'applications.review', 'applications.decide', 'applications.assign', 'applications.reopen', 'documents.view', 'periods.manage', 'content.manage', 'users.manage', 'staff.credentials', 'audit.view', 'reports.view', 'operations.view'],
        'supervisor' => ['admin.access', 'applications.view', 'applications.view_all', 'applications.review', 'applications.decide', 'applications.assign', 'applications.reopen', 'documents.view', 'reports.view'],
        'reviewer' => ['admin.access', 'applications.view', 'applications.review', 'documents.view'],
        'auditor' => ['admin.access', 'applications.view', 'applications.view_all', 'audit.view', 'reports.view'],
    ];
}

it('grants each role exactly its reviewed permissions', function (Role $role) {
    $granted = array_map(fn (Permission $permission): string => $permission->value, $role->permissions());

    expect($granted)->toBe(reviewedMatrix()[$role->value]);
})->with(Role::cases());

it('knows every permission, so a new one fails until it is reviewed', function () {
    $reviewed = array_unique(array_merge(...array_values(reviewedMatrix())));
    $known = array_map(fn (Permission $permission): string => $permission->value, Permission::cases());

    expect(array_values(array_diff($known, $reviewed)))->toBe([])
        ->and(count($known))->toBe(count($reviewed));
});

it('does not build any role from Permission::cases()', function () {
    expect(file_get_contents(app_path('Enums/Role.php')))->not->toContain('Permission::cases()');
});

it('applies a grant and a denial for one staff member', function () {
    $staff = staffWith(Role::Reviewer);
    $staff->permissionOverrides()->create(['permission' => Permission::ReportsView, 'effect' => OverrideEffect::Grant]);
    $staff->permissionOverrides()->create(['permission' => Permission::DocumentsView, 'effect' => OverrideEffect::Deny]);

    $staff = $staff->fresh();

    expect($staff->hasPermission(Permission::ReportsView))->toBeTrue()
        ->and($staff->hasPermission(Permission::DocumentsView))->toBeFalse()
        ->and($staff->hasPermission(Permission::ApplicationsReview))->toBeTrue();
});

it('ignores an exception that reached a protected permission', function (Permission $permission) {
    $staff = staffWith(Role::Reviewer);
    $staff->permissionOverrides()->create(['permission' => $permission, 'effect' => OverrideEffect::Grant]);

    expect($staff->fresh()->hasPermission($permission))->toBe(in_array($permission, Role::Reviewer->permissions(), true));
})->with([Permission::UsersManage, Permission::OperationsView]);

it('ignores exceptions on administrators and deactivated staff', function () {
    $admin = staffWith(Role::Administrator);
    $admin->permissionOverrides()->create(['permission' => Permission::AuditView, 'effect' => OverrideEffect::Deny]);

    $inactive = staffWith(Role::Reviewer);
    $inactive->permissionOverrides()->create(['permission' => Permission::ReportsView, 'effect' => OverrideEffect::Grant]);
    $inactive->forceFill(['deactivated_at' => now()])->save();

    expect($admin->fresh()->hasPermission(Permission::AuditView))->toBeTrue()
        ->and($inactive->fresh()->hasPermission(Permission::ReportsView))->toBeFalse();
});

it('saves exceptions, audits them and ends the sessions', function () {
    $staff = staffWith(Role::Reviewer);

    $this->actingAs(staffWith(Role::Administrator))
        ->put(route('admin.users.permissions', $staff), ['overrides' => ['reports.view' => 'grant', 'documents.view' => 'deny']])
        ->assertSessionHasNoErrors();

    $event = AuditEvent::query()->where('action', 'staff.permissions_changed')->sole();

    expect($staff->fresh()->hasPermission(Permission::ReportsView))->toBeTrue()
        ->and($event->metadata)->toBe(['before' => [], 'after' => ['reports.view' => 'grant', 'documents.view' => 'deny']]);
});

it('rejects exceptions on protected permissions', function (string $permission) {
    $staff = staffWith(Role::Reviewer);

    $this->actingAs(staffWith(Role::Administrator))
        ->put(route('admin.users.permissions', $staff), ['overrides' => [$permission => 'grant']])
        ->assertSessionHasErrors('overrides');

    expect($staff->permissionOverrides()->count())->toBe(0);
})->with(['users.manage', 'staff.credentials', 'admin.access', 'operations.view']);

it('refuses exceptions for oneself, for an administrator and for a student', function () {
    $admin = staffWith(Role::Administrator);
    $other = staffWith(Role::Administrator);

    foreach ([$admin, $other, applicant()] as $target) {
        $this->actingAs($admin)
            ->put(route('admin.users.permissions', $target), ['overrides' => ['reports.view' => 'grant']])
            ->assertSessionHasErrors();

        expect($target->permissionOverrides()->count())->toBe(0);
    }
});

it('lets only administrators change exceptions', function () {
    $this->actingAs(staffWith(Role::Supervisor))
        ->put(route('admin.users.permissions', staffWith(Role::Reviewer)), ['overrides' => []])
        ->assertForbidden();
});

it('lets an administrator decide but never receive assignments', function () {
    $admin = staffWith(Role::Administrator);
    $reviewer = staffWith(Role::Reviewer);

    expect($admin->canBeAssignedApplications())->toBeFalse()
        ->and($reviewer->canBeAssignedApplications())->toBeTrue();

    $application = Application::factory()->submitted()->create();

    $this->actingAs($admin)
        ->post(route('admin.applications.assign', $application), ['assignee_id' => $admin->id])
        ->assertSessionHasErrors();
});

it('protects the last active administrator', function () {
    $admin = staffWith(Role::Administrator);
    $other = User::factory()->staff(Role::Administrator)->deactivated()->create();

    $this->actingAs($admin)->put(route('admin.users.status', $admin), ['active' => false])->assertSessionHasErrors();

    expect($admin->fresh()->isActive())->toBeTrue()
        ->and($other->fresh()->isActive())->toBeFalse();
});

it('lets an administrator remove another while one remains', function () {
    $admin = staffWith(Role::Administrator);
    $other = staffWith(Role::Administrator);

    $this->actingAs($admin)->put(route('admin.users.roles', $other), ['roles' => ['reviewer']])->assertSessionHasNoErrors();

    expect($other->fresh()->hasRole(Role::Administrator))->toBeFalse();
});
