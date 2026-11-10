<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser a student has signed in from with their access code and chose to
 * remember. Only a SHA-256 hash of the cookie token is stored.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string|null $user_agent
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class TrustedDevice extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['token_hash', 'user_agent', 'last_used_at', 'expires_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<TrustedDevice>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
