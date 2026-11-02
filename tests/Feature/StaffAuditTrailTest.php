<?php

use App\Actions\Staff\ManageStaffAccess;
use App\Enums\InvitationPurpose;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;

const NEW_PASSWORD = 'New-Password-123!';

function audited(string $action): bool
{
    return AuditEvent::query()->where('action', $action)->exists();
}

beforeEach(function () {
    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true, 'secret-length' => 32]);
});

it('records the confirmation of two-factor enrolment without the secret', function () {
    $staff = User::factory()->staff(Role::Reviewer)->withoutTwoFactor()->create();

    $this->actingAs($staff)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'));

    $secret = decrypt($staff->fresh()->two_factor_secret);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.confirm'), ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
        ->assertSessionHasNoErrors();

    expect($staff->fresh()->two_factor_confirmed_at)->not->toBeNull()
        ->and(audited('auth.two_factor_confirmed'))->toBeTrue()
        ->and(AuditEvent::query()->get()->toJson())->not->toContain($secret);
});

it('records a wrong code at the sign-in challenge', function () {
    $staff = staffWith(Role::Reviewer);
    $staff->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')])->save();

    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password']);
    $this->post(route('two-factor.login.store'), ['code' => '000000']);

    $event = AuditEvent::query()->where('action', 'auth.two_factor_failed')->sole();

    expect($event->subject_id)->toBe($staff->id)
        ->and($event->metadata)->toBeNull();
});

it('records a password change and ends the other sessions', function () {
    $staff = staffWith(Role::Reviewer);

    DB::table('staff_sessions')->insert(['id' => 'other-device', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($staff)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
    ])->assertSessionHasNoErrors();

    expect(Hash::check(NEW_PASSWORD, $staff->fresh()->password))->toBeTrue()
        ->and(audited('auth.password_changed'))->toBeTrue()
        ->and(DB::table('staff_sessions')->where('id', 'other-device')->exists())->toBeFalse()
        ->and(AuditEvent::query()->get()->toJson())->not->toContain(NEW_PASSWORD);
});

it('covers every sensitive staff action in the audit trail', function () {
    $admin = staffWith(Role::Administrator);
    $staff = staffWith(Role::Reviewer);
    $access = app(ManageStaffAccess::class);

    $created = $access->create('موظف جديد', 'new@example.com', [Role::Reviewer], $admin);
    $url = $access->issueInvitation($created, InvitationPurpose::Activate, $admin);
    $access->acceptInvitation(substr($url, strpos($url, '#') + 1), NEW_PASSWORD);
    $access->syncRoles($staff, [Role::Supervisor], $admin);
    $access->syncOverrides($staff, [], $admin);
    $access->resetTwoFactor($staff, $admin);
    $access->setActive($staff, false, $admin);
    $access->setActive($staff, true, $admin);

    foreach ([
        'staff.created',
        'staff.invitation_issued',
        'staff.invitation_used',
        'staff.roles_changed',
        'staff.permissions_changed',
        'staff.two_factor_reset',
        'user.deactivated',
        'user.reactivated',
    ] as $action) {
        expect(audited($action))->toBeTrue($action);
    }

    expect(AuditEvent::query()->where('action', 'staff.created')->sole()->actor_id)->toBe($admin->id);
});
