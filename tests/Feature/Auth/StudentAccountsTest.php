<?php

use App\Enums\Role;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Students\StudentIdentity;
use App\Services\Students\TrustedDevices;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Student sign-in (ADR-0009): one identifier, then a remembered device or
 * the private access code.
 */
function trustedCookieFor(User $user): string
{
    $token = 'test-token-'.$user->id.'-'.Str::random(8);
    $device = $user->trustedDevices()->create([
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDays(30),
    ]);

    return $device->id.'|'.$token;
}

test('the login page offers identifier sign-in without password or passkey', function () {
    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/login')
        ->missing('canResetPassword'));
});

test('each identifier on a remembered device signs into the same account', function (string $field) {
    $user = User::factory()->student()->create(['username' => 'ali.student', 'phone' => '07701234567', 'student_number' => 54321]);
    $cookie = trustedCookieFor($user);
    $identifier = ['username' => 'Ali.Student', 'phone' => '0770 123 4567', 'student_number' => '54321'][$field];

    $this->withCookie(TrustedDevices::COOKIE, $cookie)
        ->post(route('student.login'), ['identifier' => $identifier])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1);
})->with(['username', 'phone', 'student_number']);

test('an identifier alone on a new device asks for the access code', function () {
    $user = User::factory()->student()->create();

    $this->post(route('student.login'), ['identifier' => $user->username])
        ->assertRedirect(route('student.code', absolute: false));

    $this->assertGuest();
});

test('the correct access code signs in and can remember the device', function () {
    $user = User::factory()->student()->create();

    $this->post(route('student.login'), ['identifier' => (string) $user->student_number]);

    $this->post(route('student.verify'), ['access_code' => 'abcde23456', 'trust_device' => '1'])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertCookie(TrustedDevices::COOKIE);

    $this->assertAuthenticatedAs($user);
    expect($user->trustedDevices()->count())->toBe(1);
});

test('remember me keeps the student signed in after the browser closes', function () {
    $user = User::factory()->student()->create();

    $this->post(route('student.login'), ['identifier' => $user->username, 'remember' => '1']);

    $response = $this->post(route('student.verify'), ['access_code' => 'abcde23456'])
        ->assertRedirect(route('dashboard', absolute: false));

    $response->assertCookie(auth()->guard('web')->getRecallerName());
    expect($user->fresh()->remember_token)->not->toBeNull();
});

test('without remember me the sign-in lasts only for the session', function () {
    $user = User::factory()->student()->create();

    $this->withCookie(TrustedDevices::COOKIE, trustedCookieFor($user))
        ->post(route('student.login'), ['identifier' => $user->username])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertCookieMissing(auth()->guard('web')->getRecallerName());
});

test('revoking devices also ends remember me sessions', function () {
    $user = User::factory()->student()->create(['remember_token' => 'old-token']);

    app(TrustedDevices::class)->revokeAll($user);

    expect($user->fresh()->remember_token)->not->toBe('old-token');
});

test('a wrong access code fails with a generic message and is audited', function () {
    $user = User::factory()->student()->create();

    $this->post(route('student.login'), ['identifier' => $user->phone]);

    $this->post(route('student.verify'), ['access_code' => 'WRONG-CODE9'])
        ->assertSessionHasErrors(['access_code' => __('auth.access_code_failed')]);

    $this->assertGuest();
    expect(AuditEvent::query()->where('action', 'auth.failed')->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('unknown identifiers get the same code step so accounts cannot be enumerated', function () {
    $this->post(route('student.login'), ['identifier' => 'nobody.here'])
        ->assertRedirect(route('student.code', absolute: false));

    $this->post(route('student.verify'), ['access_code' => 'ABCDE-23456'])
        ->assertSessionHasErrors(['access_code' => __('auth.access_code_failed')]);

    $this->assertGuest();
});

test('access code guessing is locked per account', function () {
    $user = User::factory()->student()->create();
    RateLimiter::increment('student-code|'.$user->id, amount: (int) config('examis.students.access_code_attempts_per_hour'));

    $this->post(route('student.login'), ['identifier' => $user->username]);

    $this->post(route('student.verify'), ['access_code' => 'ABCDE-23456'])
        ->assertSessionHasErrors(['access_code' => __('auth.access_code_locked')]);

    $this->assertGuest();
});

test('a remembered device of another account does not open this one', function () {
    $owner = User::factory()->student()->create();
    $other = User::factory()->student()->create();

    $this->withCookie(TrustedDevices::COOKIE, trustedCookieFor($owner))
        ->post(route('student.login'), ['identifier' => $other->username])
        ->assertRedirect(route('student.code', absolute: false));

    $this->assertGuest();
});

test('revoked or expired devices are not remembered', function () {
    $user = User::factory()->student()->create();
    $cookie = trustedCookieFor($user);
    $user->trustedDevices()->update(['revoked_at' => now()]);

    $this->withCookie(TrustedDevices::COOKIE, $cookie)
        ->post(route('student.login'), ['identifier' => $user->username])
        ->assertRedirect(route('student.code', absolute: false));
});

test('deactivated students and staff cannot use the student sign-in', function () {
    $deactivated = User::factory()->student()->deactivated()->create();
    $staff = User::factory()->staff(Role::Administrator)->create(['username' => 'staff.member']);

    foreach ([[$deactivated, $deactivated->username], [$staff, 'staff.member']] as [$user, $identifier]) {
        $this->withCookie(TrustedDevices::COOKIE, trustedCookieFor($user))
            ->post(route('student.login'), ['identifier' => $identifier])
            ->assertRedirect(route('student.code', absolute: false));

        $this->post(route('student.verify'), ['access_code' => 'ABCDE-23456'])->assertSessionHasErrors('access_code');
        $this->assertGuest();
    }
});

test('the QR link carries no personal data and leads to the same checks', function () {
    $user = User::factory()->student()->create();

    expect(route('student.qr', $user->public_id))
        ->not->toContain($user->username)
        ->not->toContain((string) $user->phone)
        ->not->toContain((string) $user->student_number);

    $this->get(route('student.qr', $user->public_id))->assertRedirect(route('student.code', absolute: false));
    $this->assertGuest();

    $this->withCookie(TrustedDevices::COOKIE, trustedCookieFor($user))
        ->get(route('student.qr', $user->public_id))
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('staff still sign in with a password at the staff login', function () {
    $staff = User::factory()->staff(Role::Administrator)->create();

    $this->get(route('staff.login'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/staff-login'));

    $this->post(route('login.store'), ['username' => $staff->username, 'password' => 'password']);
    $this->assertAuthenticatedAs($staff);
});

test('password-less students cannot sign in through the password form', function () {
    $user = User::factory()->student()->create();

    $this->post(route('login.store'), ['username' => $user->username, 'password' => '']);
    $this->assertGuest();
});

test('regenerating the access code replaces it and signs out other devices', function () {
    $user = User::factory()->student()->create();
    $current = trustedCookieFor($user);
    trustedCookieFor($user->fresh());

    $this->actingAs($user)
        ->withCookie(TrustedDevices::COOKIE, $current)
        ->post(route('student.access-code'))
        ->assertRedirect(route('student.card'));

    $user->refresh();
    expect(app(StudentIdentity::class)->checkAccessCode($user, 'ABCDE-23456'))->toBeFalse()
        ->and($user->trustedDevices()->usable()->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'student.access_code_regenerated')->exists())->toBeTrue();
});

test('administrators can reset a student access code; reviewers cannot', function () {
    $student = User::factory()->student()->create();
    trustedCookieFor($student);
    $admin = User::factory()->staff(Role::Administrator)->create();
    $reviewer = User::factory()->staff(Role::Reviewer)->create();

    $this->actingAs($reviewer)->post(route('admin.students.access-code', $student))->assertForbidden();

    $this->actingAs($admin)->post(route('admin.students.access-code', $student))
        ->assertRedirect()
        ->assertInertiaFlash('student_access_code.user_id', $student->id);

    $student->refresh();
    expect(app(StudentIdentity::class)->checkAccessCode($student, 'ABCDE-23456'))->toBeFalse()
        ->and($student->trustedDevices()->usable()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'student.access_code_reset')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

test('students cannot change application decisions', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->submitted()->create();

    $this->actingAs($student)
        ->post(route('admin.applications.transition', $application), ['target' => 'accepted'])
        ->assertForbidden();
});

test('student numbers are five digits, unique and permanent', function () {
    $identity = app(StudentIdentity::class);
    $users = User::factory()->count(30)->create();
    $users->each(fn (User $user) => $identity->ensureIdentity($user));

    $numbers = $users->map->fresh()->pluck('student_number');
    expect($numbers->unique()->count())->toBe(30)
        ->and($numbers->every(fn (int $number) => $number >= 10000 && $number <= 99999))->toBeTrue();

    $user = $users->first()->fresh();
    $user->update(['username' => 'renamed.user', 'phone' => '07709999999']);
    expect($user->fresh()->student_number)->toBe($numbers->first());

    expect(fn () => $user->forceFill(['student_number' => 11111])->save())->toThrow(LogicException::class);
});

test('allocation falls back to scanning and stops safely when the range is exhausted', function () {
    config(['examis.students.student_number_min' => 10000, 'examis.students.student_number_max' => 10002]);
    $identity = app(StudentIdentity::class);

    $users = User::factory()->count(4)->create();
    $assigned = $users->map(fn (User $user) => $identity->assignStudentNumber($user));

    expect($assigned->filter()->sort()->values()->all())->toBe([10000, 10001, 10002])
        ->and($assigned->last())->toBeNull();
});

test('the student card is only for students and shows the permanent identity', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)->get(route('student.card'))->assertInertia(fn (Assert $page) => $page
        ->component('applicant/student-card')
        ->where('student.student_number', $student->student_number)
        ->where('accessCode', null));

    $this->actingAs(User::factory()->staff(Role::Administrator)->create())
        ->get(route('student.card'))
        ->assertRedirect(route('dashboard'));
});

test('password-less students are sent from security settings to their card', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('security.edit'))
        ->assertRedirect(route('student.card'));
});

test('codes issued before plain-text storage are still accepted', function () {
    $user = User::factory()->student()->create();
    $user->forceFill(['access_code' => Hash::make('ABCDE-23456')])->save();

    $identity = app(StudentIdentity::class);

    expect($identity->checkAccessCode($user->fresh(), 'abcde23456'))->toBeTrue()
        ->and($identity->checkAccessCode($user->fresh(), 'ABCDE-23457'))->toBeFalse();
});
