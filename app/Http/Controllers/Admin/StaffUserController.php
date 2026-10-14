<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Staff\ManageStaffAccess;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StaffUserController extends Controller
{
    use ProfileValidationRules;

    public function index(): Response
    {
        $staff = User::query()
            ->staff()
            ->with('roleAssignments')
            ->orderBy('name')
            ->paginate(25)
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roleAssignments->map(fn (RoleAssignment $assignment): string => $assignment->role->value)->values(),
                'is_active' => $user->isActive(),
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/index', [
            'staff' => $staff,
            'roles' => collect(Role::cases())->map(fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()]),
        ]);
    }

    public function store(Request $request, ManageStaffAccess $access): RedirectResponse
    {
        $validated = $request->validate([
            'username' => $this->usernameRules(),
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(Role::class), 'distinct'],
        ]);

        $access->create($validated['username'], $validated['name'], $validated['email'], $this->roles($validated['roles']), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.staff_created')]);

        return to_route('admin.users.index');
    }

    public function updateRoles(Request $request, User $user, ManageStaffAccess $access): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => [Rule::enum(Role::class), 'distinct'],
        ]);

        $access->syncRoles($user, $this->roles($validated['roles']), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.saved')]);

        return to_route('admin.users.index');
    }

    public function updateStatus(Request $request, User $user, ManageStaffAccess $access): RedirectResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $access->setActive($user, (bool) $validated['active'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.saved')]);

        return back();
    }

    /**
     * @param  list<string>  $values
     * @return list<Role>
     */
    private function roles(array $values): array
    {
        return array_map(fn (string $value): Role => Role::from($value), $values);
    }
}
