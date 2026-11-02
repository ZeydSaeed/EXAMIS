<?php

use App\Enums\AccountType;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('creates a staff account and shows its one-time link once', function () {
    $this->actingAs(staffWith(Role::Administrator))
        ->post(route('admin.users.store'), [
            'name' => 'مدقق جديد',
            'email' => 'reviewer@example.com',
            'phone' => '٠٧٧٠ 123 4567',
            'roles' => ['reviewer'],
        ])
        ->assertSessionHasNoErrors()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/invitation')
            ->where('invitee.email', 'reviewer@example.com')
            ->where('invitationUrl', fn (string $url): bool => str_contains($url, '/staff/invitation#')));

    $staff = User::query()->where('email', 'reviewer@example.com')->sole();

    expect($staff->account_type)->toBe(AccountType::Staff)
        ->and($staff->hasRole(Role::Reviewer))->toBeTrue()
        ->and($staff->phone)->toBe('07701234567')
        ->and(AuditEvent::query()->where('action', 'staff.created')->exists())->toBeTrue();
});

it('refuses a staff email already in use, in any letter case', function () {
    staffWith(Role::Reviewer)->forceFill(['email' => 'taken@example.com'])->save();

    $this->actingAs(staffWith(Role::Administrator))
        ->post(route('admin.users.store'), ['name' => 'موظف جديد', 'email' => 'TAKEN@example.com', 'roles' => ['reviewer']])
        ->assertSessionHasErrors('email');
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

    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password']);
    $this->assertGuest();
});

it('ends the current session of a deactivated user on their next request', function () {
    $user = applicant();
    $user->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('apply.terms'));
    $this->assertGuest();
});

it('only administrators manage staff', function () {
    $this->actingAs(staffWith(Role::Supervisor))
        ->post(route('admin.users.store'), ['name' => 'xxx', 'email' => 'x@example.com', 'roles' => ['administrator']])
        ->assertForbidden();

    expect(User::query()->where('email', 'x@example.com')->exists())->toBeFalse();
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

it('asks for a mobile number when adding a staff member', function () {
    $this->actingAs(staffWith(Role::Administrator))
        ->post(route('admin.users.store'), ['name' => 'موظف جديد', 'email' => 'new@example.com', 'roles' => ['reviewer']])
        ->assertSessionHasErrors('phone');
});

it('lets an administrator set a mobile number, their own included, and audits it', function () {
    $admin = staffWith(Role::Administrator);
    $staff = staffWith(Role::Reviewer);

    $this->actingAs($admin)->put(route('admin.users.phone', $staff), ['phone' => '07701234567'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(route('admin.users.phone', $admin), ['phone' => '07801234567'])->assertSessionHasNoErrors();

    expect($staff->fresh()->phone)->toBe('07701234567')
        ->and($admin->fresh()->phone)->toBe('07801234567')
        ->and(AuditEvent::query()->where('action', 'staff.phone_changed')->count())->toBe(2)
        ->and(AuditEvent::query()->where('action', 'staff.phone_changed')->first()->metadata)->toBeNull();

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('staff.data.0.phone', fn (?string $phone): bool => $phone !== null));
});

it('refuses a malformed mobile number and a student account', function () {
    $admin = staffWith(Role::Administrator);

    $this->actingAs($admin)->put(route('admin.users.phone', staffWith(Role::Reviewer)), ['phone' => '123'])
        ->assertSessionHasErrors('phone');

    $student = User::factory()->student()->create();

    $this->actingAs($admin)->put(route('admin.users.phone', $student), ['phone' => '07701234567'])
        ->assertSessionHasErrors();

    expect($student->fresh()->phone)->not->toBe('07701234567');
});

it('keeps the mobile number page to those who manage users', function () {
    $staff = staffWith(Role::Reviewer);

    $this->actingAs(staffWith(Role::Supervisor))->put(route('admin.users.phone', $staff), ['phone' => '07701234567'])->assertForbidden();
    $this->actingAs($staff)->put(route('admin.users.phone', $staff), ['phone' => '07701234567'])->assertForbidden();
});
