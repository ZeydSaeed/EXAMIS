<?php

namespace App\Http\Resources;

use App\Models\ApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationDocument
 */
class ApplicationDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'document_type' => $this->whenLoaded('documentType', fn (): string => $this->documentType->code),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason !== null ? __('documents.rejection.'.$this->rejection_reason) : null,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'url' => $this->isDownloadable() ? route('documents.show', $this->resource) : null,
        ];
    }
}
