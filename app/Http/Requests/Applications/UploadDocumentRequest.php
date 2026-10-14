<?php

namespace App\Http\Requests\Applications;

use App\Models\Application;
use App\Models\DocumentType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Upload validation: extension allow-list AND content-sniffed MIME type
 * (`mimetypes` inspects the bytes), per-type size limit. Structural and
 * malware scanning happens afterwards in ScanApplicationDocument.
 */
class UploadDocumentRequest extends FormRequest
{
    private ?DocumentType $documentType = null;

    public function authorize(): bool
    {
        Gate::authorize('update', $this->application());

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->documentType();
        $mimeTypes = $type?->acceptedMimeTypes() ?? DocumentType::SUPPORTED_MIME_TYPES;
        $extensions = collect($mimeTypes)
            ->flatMap(fn (string $mime): array => match ($mime) {
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'application/pdf' => ['pdf'],
                default => [],
            })
            ->all();

        return [
            'document_type' => [
                'required', 'string',
                function (string $attribute, mixed $value, Closure $fail) use ($type): void {
                    $requiredByPeriod = $type !== null && $this->application()->examPeriod
                        ->documentTypes()
                        ->whereKey($type->id)
                        ->exists();

                    if (! $requiredByPeriod) {
                        $fail(__('validation.in', ['attribute' => __('validation.attributes.document_type')]));
                    }
                },
            ],
            'file' => [
                'required', 'file',
                'max:'.($type === null ? 1 : $type->max_size_kb),
                'extensions:'.implode(',', $extensions),
                'mimetypes:'.implode(',', $mimeTypes),
            ],
        ];
    }

    public function documentType(): ?DocumentType
    {
        return $this->documentType ??= DocumentType::query()
            ->where('code', (string) $this->input('document_type'))
            ->first();
    }

    public function application(): Application
    {
        /** @var Application */
        return $this->route('application');
    }
}
