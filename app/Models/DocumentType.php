<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property string $code
 * @property string $name_ar
 * @property string $name_en
 * @property string|null $description_ar
 * @property string|null $description_en
 * @property list<string> $allowed_mime_types
 * @property int $max_size_kb
 * @property bool $is_photo
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Pivot&object{is_required: bool|int} $pivot
 */
#[Fillable(['code', 'name_ar', 'name_en', 'description_ar', 'description_en', 'allowed_mime_types', 'max_size_kb', 'is_photo'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    /**
     * MIME types EXAMIS can ever accept, regardless of document type configuration.
     */
    public const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_mime_types' => 'array',
            'max_size_kb' => 'integer',
            'is_photo' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public function acceptedMimeTypes(): array
    {
        return array_values(array_intersect($this->allowed_mime_types, self::SUPPORTED_MIME_TYPES));
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_ar;
    }
}
