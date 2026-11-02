<?php

namespace App\Actions\Staff;

use App\Enums\AccountType;
use App\Enums\OverrideEffect;
use App\Enums\Role;
use App\Exceptions\DomainRuleViolation;
use App\Models\RoleAssignment;
use App\Models\StaffPermissionOverride;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
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
     * The new staff member sets their own password through the reset link;
     * no administrator ever knows it.
     *
     * @param  list<Role>  $roles
     */
    public function create(string $username, string $name, string $email, array $roles, User $actor): User
    {
        $user = DB::transaction(function () use ($username, $name, $email, $roles, $actor): User {
            $user = new User(['username' => Str::lower($username), 'name' => $name, 'email' => Str::lower($email), 'password' => Str::random(64)]);
            $user->account_type = AccountType::Staff;
            $user->save();

            foreach ($roles as $role) {
                $user->roleAssignments()->create(['role' => $role, 'granted_by' => $actor->id]);
            }

            $this->audit->record('staff.created', $user, ['roles' => array_map(fn (Role $role): string => $role->value, $roles)], $actor);

            return $user;
        });

        Password::broker()->sendResetLink(['username' => $user->username]);

        return $user;
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
