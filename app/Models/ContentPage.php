<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContentPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Body text is stored as plain text with blank-line paragraphs and is always
 * rendered escaped; no HTML from the database ever reaches the page.
 *
 * @property int $id
 * @property string $slug
 * @property string $title_ar
 * @property string $title_en
 * @property string $body_ar
 * @property string $body_en
 * @property bool $is_published
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['slug', 'title_ar', 'title_en', 'body_ar', 'body_en', 'is_published'])]
class ContentPage extends Model
{
    /** @use HasFactory<ContentPageFactory> */
    use HasFactory;

    /**
     * Slugs the public portal links to.
     */
    public const PUBLIC_SLUGS = ['about', 'instructions', 'eligibility', 'documents', 'faq', 'privacy', 'terms', 'help'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function localizedTitle(): string
    {
        return app()->getLocale() === 'en' ? $this->title_en : $this->title_ar;
    }

    public function localizedBody(): string
    {
        return app()->getLocale() === 'en' ? $this->body_en : $this->body_ar;
    }
}
