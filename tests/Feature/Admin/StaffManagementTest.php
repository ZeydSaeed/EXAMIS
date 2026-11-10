<?php

use App\Enums\AccountType;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('creates a staff account with the email and password the administrator enters', function () {
    $this->actingAs(staffWith(Role::Administrator))
        ->post(route('admin.users.store'), [
            'name' => 'مدقق جديد',
            'email' => 'Reviewer@example.com',
            'phone' => '٠٧٧٠ 123 4567',
            'password' => 'Staff-Password-123!',
            'password_confirmation' => 'Staff-Password-123!',
            'roles' => ['reviewer'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.users.index'));

    $staff = User::query()->where('email', 'reviewer@example.com')->sole();

    expect($staff->account_type)->toBe(AccountType::Staff)
        ->and($staff->hasRole(Role::Reviewer))->toBeTrue()
        ->and($staff->phone)->toBe('07701234567')
        ->and(Hash::check('Staff-Password-123!', $staff->password))->toBeTrue()
        ->and($staff->invitations()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'staff.created')->exists())->toBeTrue()
        ->and(AuditEvent::query()->get()->toJson())->not->toContain('Staff-Password-123!');
});

it('asks for a confirmed password when adding a staff member', function () {
    $this->actingAs(staffWith(Role::Administrator))
        ->post(route('admin.users.store'), [
            'name' => 'موظف جديد',
            'email' => 'new@example.com',
            'phone' => '07701234567',
            'password' => 'Staff-Password-123!',
            'password_confirmation' => 'Another-Password-1!',
            'roles' => ['reviewer'],
        ])
        ->assertSessionHasErrors('password');

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

it('lets an administrator change a staff member\'s name, email and password', function () {
    config(['session.driver' => 'database']);
    $staff = staffWith(Role::Reviewer);
    DB::table('staff_sessions')->insert(['id' => 'their-device', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs(staffWith(Role::Administrator))
        ->put(route('admin.users.account', $staff), [
            'name' => 'اسم معدل',
            'email' => 'Renamed@example.com',
            'password' => 'Changed-Password-1!',
            'password_confirmation' => 'Changed-Password-1!',
        ])
        ->assertSessionHasNoErrors();

    $staff->refresh();

    expect($staff->name)->toBe('اسم معدل')
        ->and($staff->email)->toBe('renamed@example.com')
        ->and(Hash::check('Changed-Password-1!', $staff->password))->toBeTrue()
        ->and(DB::table('staff_sessions')->where('id', 'their-device')->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'staff.account_changed')->sole()->metadata['changed'] ?? null)->toBe(['name', 'email', 'password'])
        ->and(AuditEvent::query()->get()->toJson())->not->toContain('Changed-Password-1!');
});

it('keeps the password when its field is left empty', function () {
    $staff = staffWith(Role::Reviewer);
    $hash = $staff->password;

    $this->actingAs(staffWith(Role::Administrator))
        ->put(route('admin.users.account', $staff), ['name' => $staff->name, 'email' => $staff->email, 'password' => ''])
        ->assertSessionHasNoErrors();

    expect($staff->fresh()->password)->toBe($hash);
});

it('refuses another staff member\'s email, your own account and non-administrators', function () {
    $admin = staffWith(Role::Administrator);
    $staff = staffWith(Role::Reviewer);
    $other = staffWith(Role::Reviewer);

    $this->actingAs($admin)
        ->put(route('admin.users.account', $staff), ['name' => $staff->name, 'email' => strtoupper($other->email)])
        ->assertSessionHasErrors('email');

    $this->put(route('admin.users.account', $admin), ['name' => $admin->name, 'email' => $admin->email, 'password' => 'Changed-Password-1!', 'password_confirmation' => 'Changed-Password-1!'])
        ->assertSessionHasErrors();

    $this->actingAs(staffWith(Role::Supervisor))
        ->put(route('admin.users.account', $staff), ['name' => 'x x x', 'email' => $staff->email])
        ->assertForbidden();
});

it('shares what each role grants, for the permission ticks', function () {
    $this->actingAs(staffWith(Role::Administrator))
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rolePermissions.reviewer', fn ($permissions) => collect($permissions)->contains('applications.review'))
            ->where('rolePermissions.reviewer', fn ($permissions) => ! collect($permissions)->contains('reports.view')));
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
