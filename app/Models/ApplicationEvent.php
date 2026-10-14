<?php

namespace App\Models;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Models\Concerns\AppendOnly;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $application_id
 * @property int|null $actor_id
 * @property ApplicationEventType $type
 * @property ApplicationStatus|null $from_status
 * @property ApplicationStatus|null $to_status
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property-read User|null $actor
 */
class ApplicationEvent extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['actor_id', 'type', 'from_status', 'to_status', 'reason', 'metadata'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ApplicationEventType::class,
            'from_status' => ApplicationStatus::class,
            'to_status' => ApplicationStatus::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
