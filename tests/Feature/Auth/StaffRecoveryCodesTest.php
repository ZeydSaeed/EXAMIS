<?php

use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\StaffRecoveryCode;
use App\Models\User;
use App\Services\Staff\RecoveryCodes;

function codesFor(User $user): array
{
    return app(RecoveryCodes::class)->issueOnce($user);
}

function challenge(User $staff): void
{
    test()->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));
}

it('shows eight codes once and stores only keyed hashes', function () {
    $staff = staffWith(Role::Reviewer);

    $codes = $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('security.recovery-codes'))
        ->assertOk()
        ->json('codes');

    $stored = StaffRecoveryCode::query()->pluck('code_hash')->all();

    expect($codes)->toHaveCount(8)
        ->and($stored)->toHaveCount(8)
        ->and(array_intersect($codes, $stored))->toBe([])
        ->and($staff->fresh()->two_factor_recovery_codes)->toBeNull();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('security.recovery-codes'))
        ->assertForbidden();
});

it('refuses codes before enrolment is confirmed', function () {
    $this->actingAs(User::factory()->staff()->withoutTwoFactor()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('security.recovery-codes'))
        ->assertForbidden();
});

it('signs in with a code once and audits it', function () {
    $staff = staffWith(Role::Reviewer);
    $code = codesFor($staff)[0];

    challenge($staff);
    $this->post(route('two-factor.login.store'), ['recovery_code' => $code]);
    $this->assertAuthenticatedAs($staff);

    expect(AuditEvent::query()->where('action', 'auth.recovery_code_used')->sole()->metadata)->toBe(['remaining' => 7]);

    auth()->logout();

    challenge($staff);
    $this->post(route('two-factor.login.store'), ['recovery_code' => $code]);
    $this->assertGuest();
});

it('rejects a code that belongs to someone else', function () {
    $staff = staffWith(Role::Reviewer);
    $other = staffWith(Role::Reviewer);
    $code = codesFor($other)[0];

    codesFor($staff);
    challenge($staff);

    $this->post(route('two-factor.login.store'), ['recovery_code' => $code]);
    $this->assertGuest();
});

it('no longer accepts the codes Fortify keeps', function () {
    $staff = staffWith(Role::Reviewer);

    challenge($staff);

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1']);
    $this->assertGuest();
});

it('clears the codes when an administrator resets two-factor', function () {
    $staff = staffWith(Role::Reviewer);
    codesFor($staff);

    $this->actingAs(staffWith(Role::Administrator))
        ->post(route('admin.users.two-factor-reset', $staff))
        ->assertSessionHasNoErrors();

    expect($staff->staffRecoveryCodes()->count())->toBe(0);
});

it('tells the security page what is left', function () {
    $staff = staffWith(Role::Reviewer);
    app(RecoveryCodes::class)->consume($staff, codesFor($staff)[0]);

    $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn ($page) => $page->where('recoveryCodesIssued', true)->where('recoveryCodesRemaining', 7));
});
