<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A ready-made decision message. The body is plain text with placeholders the
 * decision dialog fills in; the finished text is copied onto the application,
 * so editing a template never changes a message already sent.
 *
 * @property int $id
 * @property string $title
 * @property ApplicationStatus $status
 * @property string $body
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'status', 'body'])]
class MessageTemplate extends Model
{
    /** Left for the staff member to fill in when deciding. */
    public const GAP = 'فراغ';

    /**
     * @var list<string>
     */
    public const PLACEHOLDERS = ['اسم_الطالب', 'رقم_الطالب', 'الفرع', 'الاختصاص', self::GAP];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
        ];
    }
}
