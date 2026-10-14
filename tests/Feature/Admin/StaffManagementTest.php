<?php

use App\Enums\AccountType;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['examis.security.require_staff_two_factor' => false]);
});

it('creates staff accounts that set their own password through a reset link', function () {
    Notification::fake();

    $this->actingAs(staffWith(Role::Administrator))
        ->post(route('admin.users.store'), [
            'username' => 'new.reviewer',
            'name' => 'مدقق جديد',
            'email' => 'reviewer@example.com',
            'roles' => ['reviewer'],
        ])
        ->assertSessionHasNoErrors();

    $staff = User::query()->where('username', 'new.reviewer')->sole();
    expect($staff->account_type)->toBe(AccountType::Staff)
        ->and($staff->hasRole(Role::Reviewer))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'staff.created')->exists())->toBeTrue();
    Notification::assertSentTo($staff, ResetPassword::class);
});

it('records role changes with before and after values', function () {
    $admin = staffWith(Role::Administrator);
    $staff = staffWith(Role::Reviewer);

    $this->actingAs($admin)->put(route('admin.users.roles', $staff), ['roles' => ['supervisor']])->assertSessionHasNoErrors();

    $event = AuditEvent::query()->where('action', 'staff.roles_changed')->sole();
    expect($staff->fresh()->roles())->toBe([Role::Supervisor])
        ->and($event->metadata)->toBe(['before' => ['reviewer'], 'after' => ['supervisor']]);
});

it('prevents administrators from removing their own administrator role', function () {
    $admin = staffWith(Role::Administrator);

    $this->actingAs($admin)
        ->put(route('admin.users.roles', $admin), ['roles' => ['reviewer']])
        ->assertSessionHasErrors('roles');

    expect($admin->fresh()->hasRole(Role::Administrator))->toBeTrue();
});

it('deactivates an account and blocks its next sign-in', function () {
    $staff = staffWith(Role::Reviewer);

    $this->actingAs(staffWith(Role::Administrator))
        ->put(route('admin.users.status', $staff), ['active' => false])
        ->assertSessionHasNoErrors();

    auth()->logout();

    $this->post(route('login.store'), ['username' => $staff->username, 'password' => 'password']);
    $this->assertGuest();
});

it('ends the current session of a deactivated user on their next request', function () {
    $user = applicant();
    $user->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('only administrators manage staff', function () {
    $this->actingAs(staffWith(Role::Supervisor))
        ->post(route('admin.users.store'), ['username' => 'x.y.z', 'name' => 'x', 'email' => 'x@example.com', 'roles' => ['administrator']])
        ->assertForbidden();

    expect(User::query()->where('username', 'x.y.z')->exists())->toBeFalse();
});
