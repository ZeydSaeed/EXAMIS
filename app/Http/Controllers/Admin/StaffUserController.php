<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Staff\ManageStaffAccess;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StaffUserController extends Controller
{
    use ProfileValidationRules;

    /**
     * The search term matches part of the name, username or email.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'role' => ['nullable', Rule::enum(Role::class)],
            'state' => ['nullable', Rule::in(['active', 'inactive'])],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $staff = User::query()
            ->staff()
            ->with('roleAssignments')
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->whereHas('roleAssignments', fn (Builder $query) => $query->where('role', $role)))
            ->when(($filters['state'] ?? null) === 'active', fn (Builder $query) => $query->whereNull('deactivated_at'))
            ->when(($filters['state'] ?? null) === 'inactive', fn (Builder $query) => $query->whereNotNull('deactivated_at'))
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(
                fn (Builder $query) => collect(['name', 'username', 'email'])
                    ->each(fn (string $column) => $query->orWhereLike($column, '%'.trim($term).'%')),
            ))
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString()
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
            'filters' => $filters,
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
