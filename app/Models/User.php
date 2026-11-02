<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\OverrideEffect;
use App\Enums\Permission;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string|null $username
 * @property string $name
 * @property string|null $email
 * @property string|null $password
 * @property string|null $public_id
 * @property int|null $student_number
 * @property string|null $phone
 * @property string|null $access_code
 * @property CarbonImmutable|null $access_code_issued_at
 * @property AccountType $account_type
 * @property string $locale
 * @property CarbonImmutable|null $deactivated_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['username', 'name', 'email', 'password', 'phone', 'locale'])]
#[Hidden(['password', 'access_code', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'account_type' => 'applicant',
        'locale' => 'ar',
    ];

    /**
     * Memoized roles for the lifetime of this model instance.
     *
     * @var list<Role>|null
     */
    private ?array $resolvedRoles = null;

    /**
     * Memoized exceptions for the lifetime of this model instance.
     *
     * @var array<string, OverrideEffect>|null
     */
    private ?array $resolvedOverrides = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'account_type' => AccountType::class,
            'deactivated_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'access_code_issued_at' => 'datetime',
            'student_number' => 'integer',
        ];
    }

    /**
     * The student number and QR identity are permanent once assigned: they
     * are printed on cards and forms, so profile edits must never move them.
     */
    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            foreach (['student_number', 'public_id'] as $attribute) {
                if ($user->getOriginal($attribute) !== null && $user->isDirty($attribute)) {
                    throw new \LogicException("users.{$attribute} is immutable once assigned.");
                }
            }
        });
    }

    /**
     * @return HasMany<TrustedDevice, $this>
     */
    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /**
     * @return HasMany<StaffPermissionOverride, $this>
     */
    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(StaffPermissionOverride::class);
    }

    /**
     * @return HasMany<StaffInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(StaffInvitation::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeStaff(Builder $query): void
    {
        $query->where('account_type', AccountType::Staff);
    }

    public function isStaff(): bool
    {
        return $this->account_type === AccountType::Staff;
    }

    public function isApplicant(): bool
    {
        return $this->account_type === AccountType::Applicant;
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null && $this->closed_at === null;
    }

    /**
     * A one-time student account that has ended (ADR-0010).
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * The sent application whose form this student has yet to print or
     * download. The account stays open, and goes nowhere else, until they do.
     */
    public function applicationAwaitingPrint(): ?Application
    {
        if (! $this->isApplicant()) {
            return null;
        }

        $application = $this->applications()->whereNotNull('reference_number')->latest('id')->first();

        return $application?->waitsOnStudent() === false ? $application : null;
    }

    /**
     * @return list<Role>
     */
    public function roles(): array
    {
        if (! $this->isStaff()) {
            return [];
        }

        return $this->resolvedRoles ??= array_values($this->roleAssignments
            ->map(fn (RoleAssignment $assignment): Role => $assignment->role)
            ->all());
    }

    public function hasRole(Role $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    /**
     * Roles decide, then a per-staff exception may change the answer. A
     * denial beats a grant by construction: each permission has one exception.
     */
    public function hasPermission(Permission $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $granted = false;

        foreach ($this->roles() as $role) {
            $granted = $granted || in_array($permission, $role->permissions(), true);
        }

        if (! $permission->isOverridable() || $this->hasRole(Role::Administrator)) {
            return $granted;
        }

        return match ($this->overrideFor($permission)) {
            OverrideEffect::Grant => true,
            OverrideEffect::Deny => false,
            null => $granted,
        };
    }

    /**
     * Applications are assigned to the people who review them, never to an
     * administrator who merely may.
     */
    public function canBeAssignedApplications(): bool
    {
        return $this->hasPermission(Permission::ApplicationsReview) && ! $this->hasRole(Role::Administrator);
    }

    /**
     * Forget memoized roles and exceptions after grants change.
     */
    public function flushRoles(): void
    {
        $this->resolvedRoles = null;
        $this->resolvedOverrides = null;
        $this->unsetRelation('roleAssignments');
        $this->unsetRelation('permissionOverrides');
    }

    private function overrideFor(Permission $permission): ?OverrideEffect
    {
        if (! $this->isStaff()) {
            return null;
        }

        $this->resolvedOverrides ??= $this->permissionOverrides
            ->mapWithKeys(fn (StaffPermissionOverride $override): array => [$override->permission->value => $override->effect])
            ->all();

        return $this->resolvedOverrides[$permission->value] ?? null;
    }

    public function preferredLocale(): string
    {
        return $this->locale;
    }
}
