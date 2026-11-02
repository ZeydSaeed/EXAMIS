<?php

use App\Enums\Role;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true, 'secret-length' => 32]);
});

function passwordConfirmed(): array
{
    return ['auth.password_confirmed_at' => time()];
}

it('does not let staff turn off their two-factor authentication', function () {
    $staff = staffWith(Role::Reviewer);

    $this->actingAs($staff)->withSession(passwordConfirmed())
        ->delete(route('two-factor.disable'))
        ->assertForbidden();

    expect($staff->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('does not let staff replace their secret with force', function () {
    $staff = staffWith(Role::Reviewer);
    $secret = $staff->two_factor_secret;

    $this->actingAs($staff)->withSession(passwordConfirmed())
        ->post(route('two-factor.enable'), ['force' => true])
        ->assertForbidden();

    expect($staff->fresh()->two_factor_secret)->toBe($secret);
});

it('does not let staff regenerate recovery codes', function () {
    $staff = staffWith(Role::Reviewer);
    $codes = $staff->two_factor_recovery_codes;

    $this->actingAs($staff)->withSession(passwordConfirmed())
        ->post(route('two-factor.regenerate-recovery-codes'))
        ->assertForbidden();

    expect($staff->fresh()->two_factor_recovery_codes)->toBe($codes);
});

it('does not reveal the secret, the QR code or any recovery code once enrolled', function (string $route) {
    $this->actingAs(staffWith(Role::Reviewer))->withSession(passwordConfirmed())
        ->getJson(route($route))
        ->assertForbidden();
})->with(['two-factor.qr-code', 'two-factor.secret-key', 'two-factor.recovery-codes']);

it('lets an unenrolled staff member start setup with a strong standard secret', function () {
    $staff = User::factory()->staff(Role::Reviewer)->withoutTwoFactor()->create();

    $this->actingAs($staff)->withSession(passwordConfirmed())
        ->post(route('two-factor.enable'))
        ->assertRedirect();

    $secret = decrypt($staff->fresh()->two_factor_secret);

    expect(strlen($secret))->toBe(32)
        ->and($secret)->toMatch('/^[A-Z2-7]+$/');

    $url = $this->withSession(passwordConfirmed())->getJson(route('two-factor.qr-code'))->assertOk()->json('url');

    expect($url)->toStartWith('otpauth://totp/')->toContain('secret='.$secret);
});

it('sends unenrolled staff to the security page from every staff area', function (string $url) {
    $staff = User::factory()->staff(Role::Administrator)->withoutTwoFactor()->create();

    $this->actingAs($staff)->get($url)->assertRedirect(route('security.edit'));
})->with(['/admin', '/admin/users', '/dashboard', '/settings/profile', '/notifications']);

it('blocks documents and forms for unenrolled staff', function () {
    $staff = User::factory()->staff(Role::Supervisor)->withoutTwoFactor()->create();
    $document = ApplicationDocument::factory()->verified()->create();
    $application = Application::factory()->submitted()->create();

    $this->actingAs($staff)->get(route('documents.show', $document))->assertRedirect(route('security.edit'));
    $this->actingAs($staff)->get(route('applications.receipt', $application))->assertRedirect(route('security.edit'));
});

it('still lets an unenrolled staff member reach setup and sign out', function () {
    $staff = User::factory()->staff(Role::Reviewer)->withoutTwoFactor()->create();

    $this->actingAs($staff)->withSession(passwordConfirmed())->get(route('security.edit'))->assertOk();
    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

it('never remembers a staff sign-in', function () {
    $staff = User::factory()->staff(Role::Reviewer)->withoutTwoFactor()->create();
    $token = $staff->remember_token;

    $response = $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password', 'remember' => 'on']);

    $this->assertAuthenticatedAs($staff);
    expect($staff->fresh()->remember_token)->toBe($token)
        ->and(collect($response->headers->getCookies())->filter(fn ($cookie): bool => str_starts_with($cookie->getName(), 'remember_')))->toBeEmpty();
});

it('challenges an enrolled staff member with remember off', function () {
    $staff = staffWith(Role::Reviewer);

    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password', 'remember' => 'on'])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.remember', false);

    $this->assertGuest();
});

it('stops staff changing their own roles in any direction', function () {
    $admin = staffWith(Role::Administrator);

    $this->actingAs($admin)
        ->put(route('admin.users.roles', $admin), ['roles' => ['administrator', 'supervisor']])
        ->assertSessionHasErrors('roles');

    expect($admin->fresh()->roles())->toBe([Role::Administrator]);
});

it('only changes the status of staff accounts', function () {
    $student = applicant();

    $this->actingAs(staffWith(Role::Administrator))
        ->put(route('admin.users.status', $student), ['active' => false])
        ->assertSessionHasErrors();

    expect($student->fresh()->deactivated_at)->toBeNull();
});
