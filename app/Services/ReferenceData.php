<?php

namespace App\Services;

use App\Enums\ReferenceType;
use App\Models\ReferenceItem;
use Illuminate\Support\Facades\Cache;

/**
 * Cached lookup lists. The cache holds public, non-personal data only and is
 * keyed by type, never by user, so nothing can leak between applicants.
 *
 * Bound as a scoped instance: each list is read from the cache once per
 * request or job and then served from memory, so a page or export that names
 * thousands of codes costs one cache read per list, not one per code.
 */
class ReferenceData
{
    private const TTL_SECONDS = 3600;

    /**
     * @var array<string, list<array{code: string, parent_code: string|null, name_ar: string, name_en: string}>>
     */
    private array $loaded = [];

    /**
     * @return list<array{code: string, parent_code: string|null, name_ar: string, name_en: string}>
     */
    public function items(ReferenceType $type): array
    {
        return $this->loaded[$type->value] ??= Cache::remember($this->cacheKey($type), self::TTL_SECONDS, fn (): array => array_values(ReferenceItem::query()
            ->activeOfType($type)
            ->get(['code', 'parent_code', 'name_ar', 'name_en'])
            ->map(fn (ReferenceItem $item): array => [
                'code' => $item->code,
                'parent_code' => $item->parent_code,
                'name_ar' => $item->name_ar,
                'name_en' => $item->name_en,
            ])
            ->all()));
    }

    /**
     * @return list<string>
     */
    public function codes(ReferenceType $type): array
    {
        return array_column($this->items($type), 'code');
    }

    public function name(ReferenceType $type, ?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        foreach ($this->items($type) as $item) {
            if ($item['code'] === $code) {
                return app()->getLocale() === 'en' ? $item['name_en'] : $item['name_ar'];
            }
        }

        return $code;
    }

    public function forget(ReferenceType $type): void
    {
        unset($this->loaded[$type->value]);
        Cache::forget($this->cacheKey($type));
    }

    private function cacheKey(ReferenceType $type): string
    {
        return 'reference-data:'.$type->value;
    }
}
