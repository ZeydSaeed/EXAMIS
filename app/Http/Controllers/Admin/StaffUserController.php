<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Staff\ManageStaffAccess;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\OverrideEffect;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\RoleAssignment;
use App\Models\StaffPermissionOverride;
use App\Models\User;
use App\Services\Staff\CloudflareAccess;
use App\Services\Students\StudentIdentity;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class StaffUserController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * The search term matches part of the name, username or email.
     */
    public function index(Request $request, CloudflareAccess $cloudflareAccess): Response
    {
        $filters = $request->validate([
            'role' => ['nullable', Rule::enum(Role::class)],
            'state' => ['nullable', Rule::in(['active', 'inactive'])],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $staff = User::query()
            ->staff()
            ->with(['roleAssignments', 'permissionOverrides'])
            ->withExists(['invitations as invitation_pending' => fn ($query) => $query->open()])
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
                'phone' => $user->phone,
                'roles' => $user->roleAssignments->map(fn (RoleAssignment $assignment): string => $assignment->role->value)->values(),
                'overrides' => (object) $user->permissionOverrides
                    ->mapWithKeys(fn (StaffPermissionOverride $override): array => [$override->permission->value => $override->effect->value])
                    ->all(),
                'is_active' => $user->isActive(),
                'invitation_pending' => (bool) $user->getAttribute('invitation_pending'),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/index', [
            'staff' => $staff,
            // Whether the staff e-mails reached Cloudflare Access; null when it is off.
            'accessSync' => $cloudflareAccess->isConfigured() ? ($cloudflareAccess->status() ?? ['ok' => null, 'at' => null, 'count' => null]) : null,
            'filters' => $filters,
            'roles' => collect(Role::cases())->map(fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()]),
            'permissions' => collect(Permission::cases())
                ->filter(fn (Permission $permission): bool => $permission->isOverridable())
                ->map(fn (Permission $permission): array => ['value' => $permission->value, 'label' => $permission->label()])
                ->values(),
            // What each role grants, so the page can tick a staff member's permissions.
            'rolePermissions' => collect(Role::cases())->mapWithKeys(fn (Role $role): array => [
                $role->value => array_map(fn (Permission $permission): string => $permission->value, $role->permissions()),
            ]),
        ]);
    }

    public function store(Request $request, ManageStaffAccess $access): RedirectResponse
    {
        $this->normalizePhone($request);

        $validated = $request->validate([
            'name' => $this->nameRules(),
            'email' => $this->staffEmailRules(),
            'phone' => $this->phoneRules(),
            'password' => $this->passwordRules(),
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(Role::class), 'distinct'],
        ]);

        $access->create($validated['name'], $validated['email'], $this->roles($validated['roles']), $request->user(), $validated['phone'], $validated['password']);

        return to_route('admin.users.index');
    }

    /**
     * Only an administrator changes a staff member's name, sign-in email or
     * password; the password is left as it is when the field is empty.
     */
    public function updateAccount(Request $request, User $user, ManageStaffAccess $access): RedirectResponse
    {
        $validated = $request->validate([
            'name' => $this->nameRules(),
            'email' => $this->staffEmailRules(except: $user),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
        ]);

        $access->updateAccount($user, $validated['name'], $validated['email'], $validated['password'] ?? null, $request->user());

        return back();
    }

    public function updatePhone(Request $request, User $user, ManageStaffAccess $access): RedirectResponse
    {
        $this->normalizePhone($request);

        $validated = $request->validate(['phone' => $this->phoneRules()]);

        $access->setPhone($user, $validated['phone'], $request->user());

        return back();
    }

    public function updateRoles(Request $request, User $user, ManageStaffAccess $access): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => [Rule::enum(Role::class), 'distinct'],
        ]);

        $access->syncRoles($user, $this->roles($validated['roles']), $request->user());

        return to_route('admin.users.index');
    }

    public function updatePermissions(Request $request, User $user, ManageStaffAccess $access): RedirectResponse
    {
        // Permission values contain dots, which `overrides.*` rules would split.
        $validated = $request->validate([
            'overrides' => ['present', 'array', function (string $attribute, array $value, Closure $fail): void {
                foreach ($value as $permission => $effect) {
                    if (! Permission::tryFrom((string) $permission)?->isOverridable() || ! is_string($effect) || OverrideEffect::tryFrom($effect) === null) {
                        $fail(__('validation.in', ['attribute' => $attribute]));
                    }
                }
            }],
        ]);

        $access->syncOverrides($user, array_map(fn (string $effect): OverrideEffect => OverrideEffect::from($effect), $validated['overrides']), $request->user());

        return to_route('admin.users.index');
    }

    public function updateStatus(Request $request, User $user, ManageStaffAccess $access): RedirectResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $access->setActive($user, (bool) $validated['active'], $request->user());

        return back();
    }

    private function normalizePhone(Request $request): void
    {
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => StudentIdentity::normalizePhone($request->input('phone'))]);
        }
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
