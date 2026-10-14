<?php

namespace App\Models;

use App\Enums\ReferenceType;
use Carbon\CarbonImmutable;
use Database\Factories\ReferenceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property ReferenceType $type
 * @property string $code
 * @property string|null $parent_code
 * @property string $name_ar
 * @property string $name_en
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['type', 'code', 'parent_code', 'name_ar', 'name_en', 'sort_order', 'is_active'])]
class ReferenceItem extends Model
{
    /** @use HasFactory<ReferenceItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReferenceType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<ReferenceItem>  $query
     */
    public function scopeActiveOfType(Builder $query, ReferenceType $type): void
    {
        $query->where('type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_ar;
    }
}
