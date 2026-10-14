<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ApplicationDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $application_id
 * @property int $document_type_id
 * @property int $uploaded_by
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property DocumentStatus $status
 * @property string|null $rejection_reason
 * @property CarbonImmutable|null $scanned_at
 * @property CarbonImmutable|null $replaced_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Application $application
 * @property-read DocumentType $documentType
 */
class ApplicationDocument extends Model
{
    /** @use HasFactory<ApplicationDocumentFactory> */
    use HasFactory;

    /**
     * Every attribute is set explicitly by StoreApplicationDocument.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (ApplicationDocument $document): void {
            $document->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'size_bytes' => 'integer',
            'scanned_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === DocumentStatus::Verified;
    }

    public function isRejected(): bool
    {
        return $this->status === DocumentStatus::Rejected;
    }
}
