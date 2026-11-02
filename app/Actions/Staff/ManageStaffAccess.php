<?php

namespace App\Actions\Staff;

use App\Enums\AccountType;
use App\Enums\InvitationPurpose;
use App\Enums\OverrideEffect;
use App\Enums\Role;
use App\Exceptions\DomainRuleViolation;
use App\Models\RoleAssignment;
use App\Models\StaffInvitation;
use App\Models\StaffPermissionOverride;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates staff accounts and changes their roles and activation state.
 * Every change is audited, and the affected user's sessions are ended so
 * reduced privileges apply immediately.
 */
class ManageStaffAccess
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * The new staff member sets their own password through a one-time link
     * (see issueInvitation); no administrator ever knows it.
     *
     * @param  list<Role>  $roles
     */
    public function create(string $name, string $email, array $roles, ?User $actor): User
    {
        return DB::transaction(function () use ($name, $email, $roles, $actor): User {
            $user = new User(['username' => 'staff.'.Str::lower(Str::random(16)), 'name' => $name, 'email' => Str::lower($email), 'password' => Str::random(64)]);
            $user->account_type = AccountType::Staff;
            $user->save();

            foreach ($roles as $role) {
                $user->roleAssignments()->create(['role' => $role, 'granted_by' => $actor?->id]);
            }

            $this->audit->record('staff.created', $user, ['roles' => array_map(fn (Role $role): string => $role->value, $roles)], $actor);

            return $user;
        });
    }

    /**
     * Issues a one-time link and replaces any open one. The returned URL is
     * the only copy of the token: show it once and never log or store it.
     * The token sits in the fragment so it never reaches a server log.
     */
    public function issueInvitation(User $user, InvitationPurpose $purpose, ?User $actor): string
    {
        $this->ensureManageable($user, $actor);

        $token = Str::random(64);
        $hours = (int) config('examis.staff.invitation_hours');

        DB::transaction(function () use ($user, $purpose, $actor, $token, $hours): void {
            $this->revokeInvitations($user);

            $user->invitations()->create([
                'purpose' => $purpose,
                'token_hash' => StaffInvitation::hashToken($token),
                'expires_at' => now()->addHours($hours),
                'created_by' => $actor?->id,
            ]);

            $this->audit->record('staff.invitation_issued', $user, ['purpose' => $purpose->value, 'valid_hours' => $hours], $actor);
        });

        return route('staff.invitation.show').'#'.$token;
    }

    /**
     * Spends a link and sets the password its owner chose. The row lock lets
     * only one of two simultaneous uses through. Two-factor setup is left
     * alone: a new password never bypasses it.
     */
    public function acceptInvitation(string $token, string $password): void
    {
        $user = DB::transaction(function () use ($token, $password): User {
            $invitation = StaffInvitation::query()
                ->where('token_hash', StaffInvitation::hashToken($token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || ! $invitation->isOpen() || ! $invitation->user->isActive()) {
                throw new DomainRuleViolation(__('auth.invitation_unusable'), 'token');
            }

            $user = $invitation->user;

            $invitation->forceFill(['used_at' => now()])->save();
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

            $this->audit->record('staff.invitation_used', $user, ['purpose' => $invitation->purpose->value], $user);

            return $user;
        }, 3);

        $this->endSessions($user);
    }

    /**
     * For a staff member who lost their authenticator and recovery codes. They
     * enrol again at their next sign-in. Nobody resets their own.
     */
    public function resetTwoFactor(User $user, User $actor): void
    {
        $this->ensureManageable($user, $actor);

        DB::transaction(function () use ($user, $actor): void {
            $this->clearTwoFactor($user);
            $this->audit->record('staff.two_factor_reset', $user, actor: $actor);
        });

        $this->endSessions($user);
    }

    /**
     * Break-glass recovery from the server, for when no administrator can
     * sign in. Returns the one-time password link.
     */
    public function recover(User $user): string
    {
        DB::transaction(function () use ($user): void {
            $this->clearTwoFactor($user);
            $this->audit->record('staff.break_glass_recovery', $user);
        });

        $this->endSessions($user);

        return $this->issueInvitation($user, InvitationPurpose::ResetPassword, null);
    }

    /**
     * @param  list<Role>  $roles
     */
    public function syncRoles(User $user, array $roles, User $actor): void
    {
        if (! $user->isStaff()) {
            throw DomainRuleViolation::assigneeNotEligible();
        }

        if ($user->is($actor)) {
            throw new DomainRuleViolation(__('admin.errors.cannot_change_own_roles'), 'roles');
        }

        DB::transaction(function () use ($user, $roles, $actor): void {
            $this->keepAnAdministrator($user, in_array(Role::Administrator, $roles, true), $actor);

            $before = $user->roleAssignments()->pluck('role')->map(fn (Role $role): string => $role->value)->all();
            $after = array_map(fn (Role $role): string => $role->value, $roles);

            $user->roleAssignments()->whereNotIn('role', $after)->delete();

            foreach (array_diff($after, $before) as $role) {
                $user->roleAssignments()->create(['role' => $role, 'granted_by' => $actor->id]);
            }

            $this->audit->record('staff.roles_changed', $user, ['before' => $before, 'after' => $after], $actor);
        }, 3);

        $user->flushRoles();
        $this->endSessions($user);
    }

    public function setActive(User $user, bool $active, User $actor): void
    {
        if (! $user->isStaff()) {
            throw DomainRuleViolation::assigneeNotEligible();
        }

        if ($user->is($actor)) {
            throw new DomainRuleViolation(__('admin.errors.cannot_deactivate_self'), 'user');
        }

        DB::transaction(function () use ($user, $active, $actor): void {
            if (! $active) {
                $this->keepAnAdministrator($user, false, $actor);
                $this->revokeInvitations($user);
            }

            $user->forceFill(['deactivated_at' => $active ? null : now()])->save();
            $this->audit->record($active ? 'user.reactivated' : 'user.deactivated', $user, actor: $actor);
        }, 3);

        if (! $active) {
            $this->endSessions($user);
        }
    }

    /**
     * Replaces the staff member's exceptions. Administrators have none, and
     * nobody edits their own.
     *
     * @param  array<string, OverrideEffect>  $overrides  permission value => effect
     */
    public function syncOverrides(User $user, array $overrides, User $actor): void
    {
        if (! $user->isStaff()) {
            throw DomainRuleViolation::assigneeNotEligible();
        }

        if ($user->is($actor)) {
            throw new DomainRuleViolation(__('admin.errors.cannot_change_own_roles'), 'overrides');
        }

        if ($user->hasRole(Role::Administrator)) {
            throw new DomainRuleViolation(__('admin.errors.administrators_have_no_overrides'), 'overrides');
        }

        DB::transaction(function () use ($user, $overrides, $actor): void {
            $before = $user->permissionOverrides()->get()
                ->mapWithKeys(fn (StaffPermissionOverride $override): array => [$override->permission->value => $override->effect->value])
                ->all();

            $user->permissionOverrides()->delete();

            foreach ($overrides as $permission => $effect) {
                $user->permissionOverrides()->create(['permission' => $permission, 'effect' => $effect, 'granted_by' => $actor->id]);
            }

            $this->audit->record('staff.permissions_changed', $user, [
                'before' => $before,
                'after' => array_map(fn (OverrideEffect $effect): string => $effect->value, $overrides),
            ], $actor);
        });

        $user->flushRoles();
        $this->endSessions($user);
    }

    private function ensureManageable(User $user, ?User $actor): void
    {
        if (! $user->isStaff() || ! $user->isActive()) {
            throw DomainRuleViolation::assigneeNotEligible();
        }

        if ($user->is($actor)) {
            throw new DomainRuleViolation(__('admin.errors.cannot_manage_own_credentials'), 'user');
        }
    }

    private function clearTwoFactor(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        $user->staffRecoveryCodes()->delete();
    }

    private function revokeInvitations(User $user): void
    {
        $user->invitations()->open()->update(['revoked_at' => now()]);
    }

    /**
     * Stops a change that would leave no active administrator, including two
     * that race each other. The first statement waits for every active
     * administrator's row; the second reads what the winner committed, which
     * the first cannot see because its snapshot predates the wait.
     */
    private function keepAnAdministrator(User $user, bool $staysAdministrator, User $actor): void
    {
        $administrators = fn () => User::query()
            ->whereIn('id', RoleAssignment::query()->where('role', Role::Administrator)->select('user_id'))
            ->whereNull('deactivated_at')
            ->whereNull('closed_at')
            ->orderBy('id');

        $administrators()->lockForUpdate()->pluck('id');

        $active = $administrators()->pluck('id')->all();

        if (! in_array($actor->id, $active, true)) {
            throw new AuthorizationException;
        }

        if (array_diff($active, $staysAdministrator ? [] : [$user->id]) === []) {
            throw new DomainRuleViolation(__('admin.errors.last_administrator'), 'user');
        }
    }

    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
