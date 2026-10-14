<?php

namespace App\Actions\Staff;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
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

        if ($user->is($actor) && ! in_array(Role::Administrator, $roles, true)) {
            throw new DomainRuleViolation(__('admin.errors.cannot_remove_own_admin'), 'roles');
        }

        DB::transaction(function () use ($user, $roles, $actor): void {
            $before = $user->roleAssignments()->pluck('role')->map(fn (Role $role): string => $role->value)->all();
            $after = array_map(fn (Role $role): string => $role->value, $roles);

            $user->roleAssignments()->whereNotIn('role', $after)->delete();

            foreach (array_diff($after, $before) as $role) {
                $user->roleAssignments()->create(['role' => $role, 'granted_by' => $actor->id]);
            }

            $this->audit->record('staff.roles_changed', $user, ['before' => $before, 'after' => $after], $actor);
        });

        $user->flushRoles();
        $this->endSessions($user);
    }

    public function setActive(User $user, bool $active, User $actor): void
    {
        if ($user->is($actor)) {
            throw new DomainRuleViolation(__('admin.errors.cannot_deactivate_self'), 'user');
        }

        $user->forceFill(['deactivated_at' => $active ? null : now()])->save();
        $this->audit->record($active ? 'user.reactivated' : 'user.deactivated', $user, actor: $actor);

        if (! $active) {
            $this->endSessions($user);
        }
    }

    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
