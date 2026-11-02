<?php

namespace App\Models;

use App\Enums\OverrideEffect;
use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property Permission $permission
 * @property OverrideEffect $effect
 * @property int|null $granted_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['permission', 'effect', 'granted_by'])]
class StaffPermissionOverride extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permission' => Permission::class,
            'effect' => OverrideEffect::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
