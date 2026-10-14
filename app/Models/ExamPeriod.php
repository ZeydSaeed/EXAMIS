<?php

namespace App\Models;

use App\Enums\ExamPeriodStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ExamPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name_ar
 * @property string $name_en
 * @property string $academic_year
 * @property ExamPeriodStatus $status
 * @property CarbonImmutable $opens_at
 * @property CarbonImmutable $closes_at
 * @property list<string>|null $eligible_branch_codes
 * @property list<string>|null $eligible_category_codes
 * @property string|null $instructions_ar
 * @property string|null $instructions_en
 * @property int $last_sequence
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, DocumentType> $documentTypes
 */
#[Fillable([
    'code', 'name_ar', 'name_en', 'academic_year', 'status', 'opens_at', 'closes_at',
    'eligible_branch_codes', 'eligible_category_codes', 'instructions_ar', 'instructions_en',
])]
class ExamPeriod extends Model
{
    /** @use HasFactory<ExamPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExamPeriodStatus::class,
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'eligible_branch_codes' => 'array',
            'eligible_category_codes' => 'array',
            'last_sequence' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<DocumentType, $this>
     */
    public function documentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class)->withPivot('is_required');
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @param  Builder<ExamPeriod>  $query
     */
    public function scopeAcceptingApplications(Builder $query): void
    {
        $query->where('status', ExamPeriodStatus::Open)
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>', now());
    }

    public function isAcceptingApplications(): bool
    {
        return $this->status === ExamPeriodStatus::Open
            && $this->opens_at->isPast()
            && $this->closes_at->isFuture();
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_ar;
    }

    public function localizedInstructions(): ?string
    {
        return app()->getLocale() === 'en' ? $this->instructions_en : $this->instructions_ar;
    }
}
