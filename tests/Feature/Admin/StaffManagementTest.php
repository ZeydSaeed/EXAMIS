<?php

use App\Enums\AccountType;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

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

it('finds staff by part of the name, username or email, ignoring case', function () {
    $target = User::factory()->staff(Role::Reviewer)->create(['username' => 'sara.reviewer', 'name' => 'سارة علي']);
    User::factory()->staff(Role::Reviewer)->create(['username' => 'omar', 'name' => 'عمر خالد']);
    $admin = staffWith(Role::Administrator);

    foreach (['SARA.REV', 'سارة', strtoupper(explode('@', $target->email)[0])] as $term) {
        $this->actingAs($admin)->get(route('admin.users.index', ['q' => $term]))
            ->assertInertia(fn (Assert $page) => $page->where('staff.total', 1)->where('staff.data.0.id', $target->id));
    }
});

it('filters staff by role and by account state', function () {
    $supervisor = staffWith(Role::Supervisor);
    $inactive = User::factory()->staff(Role::Reviewer)->deactivated()->create();
    $admin = staffWith(Role::Administrator);

    $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'supervisor']))
        ->assertInertia(fn (Assert $page) => $page->where('staff.total', 1)->where('staff.data.0.id', $supervisor->id));

    $this->actingAs($admin)->get(route('admin.users.index', ['state' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page->where('staff.total', 1)->where('staff.data.0.id', $inactive->id));

    $this->actingAs($admin)->get(route('admin.users.index', ['state' => 'active']))
        ->assertInertia(fn (Assert $page) => $page->where('staff.total', 2));
});

it('rejects unknown staff filters and page sizes', function () {
    $this->actingAs(staffWith(Role::Administrator))
        ->get(route('admin.users.index', ['role' => 'owner', 'state' => 'gone', 'per_page' => 1000]))
        ->assertSessionHasErrors(['role', 'state', 'per_page']);
});
