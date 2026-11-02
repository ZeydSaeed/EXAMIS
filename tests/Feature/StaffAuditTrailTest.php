<?php

use App\Actions\Staff\ManageStaffAccess;
use App\Enums\InvitationPurpose;
use App\Enums\Role;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

const NEW_PASSWORD = 'New-Password-123!';

function audited(string $action): bool
{
    return AuditEvent::query()->where('action', $action)->exists();
}

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
    $access->setActive($staff, false, $admin);
    $access->setActive($staff, true, $admin);

    foreach ([
        'staff.created',
        'staff.invitation_issued',
        'staff.invitation_used',
        'staff.roles_changed',
        'staff.permissions_changed',
        'user.deactivated',
        'user.reactivated',
    ] as $action) {
        expect(audited($action))->toBeTrue($action);
    }

    expect(AuditEvent::query()->where('action', 'staff.created')->sole()->actor_id)->toBe($admin->id);
});
