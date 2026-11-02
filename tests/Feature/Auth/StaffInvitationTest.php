<?php

use App\Actions\Staff\ManageStaffAccess;
use App\Enums\InvitationPurpose;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

const STRONG_PASSWORD = 'Str0ng-Passw0rd!';

function issueLink(User $user, InvitationPurpose $purpose = InvitationPurpose::Activate, ?User $actor = null): string
{
    $url = app(ManageStaffAccess::class)->issueInvitation($user, $purpose, $actor);

    return substr($url, strpos($url, '#') + 1);
}

function invitee(): User
{
    return User::factory()->staff(Role::Reviewer)->withoutTwoFactor()->create();
}

function accept(string $token, string $password = STRONG_PASSWORD): array
{
    return ['token' => $token, 'password' => $password, 'password_confirmation' => $password];
}

it('sets the password of the invited staff member and audits it', function () {
    $staff = invitee();

    $this->post(route('staff.invitation.store'), accept(issueLink($staff)))
        ->assertRedirect(route('staff.login'));

    expect(Hash::check(STRONG_PASSWORD, $staff->fresh()->password))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'staff.invitation_issued')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'staff.invitation_used')->where('actor_id', $staff->id)->exists())->toBeTrue();
});

it('works once only', function () {
    $staff = invitee();
    $token = issueLink($staff);

    $this->post(route('staff.invitation.store'), accept($token))->assertRedirect(route('staff.login'));
    $this->post(route('staff.invitation.store'), accept($token, 'An0ther-Passw0rd!'))->assertSessionHasErrors('token');

    expect(Hash::check(STRONG_PASSWORD, $staff->fresh()->password))->toBeTrue();
});

it('stops working when it expires', function () {
    $staff = invitee();
    $token = issueLink($staff);

    $this->travel(49)->hours();

    $this->post(route('staff.invitation.store'), accept($token))->assertSessionHasErrors('token');
});

it('is replaced by a newer link and revoked when the account is deactivated', function () {
    $admin = staffWith(Role::Administrator);
    $staff = invitee();
    $first = issueLink($staff);
    $second = issueLink($staff);

    $this->post(route('staff.invitation.store'), accept($first))->assertSessionHasErrors('token');

    app(ManageStaffAccess::class)->setActive($staff, false, $admin);

    $this->post(route('staff.invitation.store'), accept($second))->assertSessionHasErrors('token');
});

it('refuses a weak password and leaves the link usable', function () {
    $staff = invitee();
    $token = issueLink($staff);

    $this->post(route('staff.invitation.store'), accept($token, 'short'))->assertSessionHasErrors('password');
    $this->post(route('staff.invitation.store'), accept($token))->assertRedirect(route('staff.login'));
});

it('stores only a hash and leaves nothing in the logs, the audit trail or the session', function () {
    Log::spy();

    $staff = invitee();
    $token = issueLink($staff);
    $hash = StaffInvitation::query()->sole()->token_hash;

    $this->post(route('staff.invitation.store'), accept($token));

    Log::shouldNotHaveReceived('info');

    expect($hash)->toBe(hash('sha256', $token))->not->toContain($token)
        ->and(AuditEvent::query()->get()->toJson())->not->toContain($token)
        ->and(json_encode(session()->all()))->not->toContain($token);
});

it('names the invitee only for a valid link', function () {
    $staff = invitee();

    $this->post(route('staff.invitation.inspect'), ['token' => issueLink($staff)])
        ->assertInertia(fn (Assert $page) => $page
            ->where('invalid', false)
            ->where('invitee.email', $staff->email));

    $this->post(route('staff.invitation.inspect'), ['token' => 'nothing'])
        ->assertInertia(fn (Assert $page) => $page->where('invalid', true)->where('invitee', null));
});

it('keeps the page free of the token on a plain visit', function () {
    $this->get(route('staff.invitation.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/staff-invitation')->missing('invitee'));
});

it('leaves two-factor in place when a password link is used', function () {
    $staff = staffWith(Role::Reviewer);

    $this->post(route('staff.invitation.store'), accept(issueLink($staff, InvitationPurpose::ResetPassword)));

    expect($staff->fresh()->two_factor_confirmed_at)->not->toBeNull();

    $this->post(route('login.store'), ['email' => $staff->email, 'password' => STRONG_PASSWORD])
        ->assertRedirect(route('two-factor.login'));
});

it('ends existing sessions when the password changes', function () {
    config(['session.driver' => 'database']);
    $staff = invitee();

    DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);

    $this->post(route('staff.invitation.store'), accept(issueLink($staff)));

    expect(DB::table('sessions')->where('user_id', $staff->id)->count())->toBe(0);
});

it('lets an administrator issue a new link and reset two-factor for someone else', function () {
    $admin = staffWith(Role::Administrator);
    $staff = staffWith(Role::Reviewer);

    $this->actingAs($admin)->post(route('admin.users.invitation', $staff))
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/invitation'));

    $this->actingAs($admin)->post(route('admin.users.two-factor-reset', $staff))->assertSessionHasNoErrors();

    $staff->refresh();

    expect($staff->two_factor_confirmed_at)->toBeNull()
        ->and($staff->two_factor_secret)->toBeNull()
        ->and(AuditEvent::query()->where('action', 'staff.two_factor_reset')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

it('refuses both on your own account', function () {
    $admin = staffWith(Role::Administrator);

    $this->actingAs($admin)->post(route('admin.users.invitation', $admin))->assertSessionHasErrors();
    $this->actingAs($admin)->post(route('admin.users.two-factor-reset', $admin))->assertSessionHasErrors();

    expect($admin->fresh()->two_factor_confirmed_at)->not->toBeNull()
        ->and($admin->invitations()->count())->toBe(0);
});

it('keeps both from everyone but administrators, and from students', function () {
    $staff = staffWith(Role::Reviewer);

    $this->actingAs(staffWith(Role::Supervisor))->post(route('admin.users.invitation', $staff))->assertForbidden();
    $this->actingAs(staffWith(Role::Supervisor))->post(route('admin.users.two-factor-reset', $staff))->assertForbidden();
    $this->actingAs(staffWith(Role::Administrator))->post(route('admin.users.invitation', applicant()))->assertSessionHasErrors();
});

it('removes the old sign-in reset endpoints', function (string $method, string $url) {
    $this->call($method, $url)->assertNotFound();
})->with([
    ['GET', '/forgot-password'],
    ['POST', '/forgot-password'],
    ['GET', '/reset-password/some-token'],
    ['POST', '/reset-password'],
]);

it('creates an administrator from the command line and prints one link', function () {
    $this->artisan('examis:create-admin', ['name' => 'مدير أساسي', 'email' => 'Primary@Example.com'])
        ->expectsOutputToContain('/staff/invitation#')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'primary@example.com')->sole();

    expect($admin->hasRole(Role::Administrator))->toBeTrue()
        ->and($admin->two_factor_confirmed_at)->toBeNull()
        ->and($admin->invitations()->open()->count())->toBe(1);
});

it('refuses a repeated or invalid email on the command line', function () {
    staffWith(Role::Reviewer)->forceFill(['email' => 'taken@example.com'])->save();

    $this->artisan('examis:create-admin', ['name' => 'x', 'email' => 'TAKEN@example.com'])->assertFailed();
    $this->artisan('examis:create-admin', ['name' => 'x', 'email' => 'not-an-email'])->assertExitCode(2);
});

it('recovers an administrator from the command line and audits it', function () {
    $admin = staffWith(Role::Administrator);

    $this->artisan('examis:recover-admin', ['email' => $admin->email])
        ->expectsConfirmation("Reset two-factor authentication for {$admin->email}?", 'yes')
        ->expectsOutputToContain('/staff/invitation#')
        ->assertSuccessful();

    expect($admin->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and(AuditEvent::query()->where('action', 'staff.break_glass_recovery')->exists())->toBeTrue();
});

it('recovers only active administrators', function () {
    $this->artisan('examis:recover-admin', ['email' => staffWith(Role::Reviewer)->email])->assertFailed();
    $this->artisan('examis:recover-admin', ['email' => 'nobody@example.com'])->assertFailed();
});
