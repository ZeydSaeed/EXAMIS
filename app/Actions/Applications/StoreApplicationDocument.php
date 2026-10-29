<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationEventType;
use App\Exceptions\DomainRuleViolation;
use App\Jobs\ScanApplicationDocument;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores an uploaded document in private storage under a random key and
 * records its metadata. A previous document of the same type is marked
 * replaced (never deleted) so the history stays auditable. The document is
 * not downloadable until ScanApplicationDocument verifies it.
 */
class StoreApplicationDocument
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Application $application, DocumentType $type, UploadedFile $file, User $actor): ApplicationDocument
    {
        $disk = (string) config('examis.documents.disk');
        $path = $application->uuid.'/'.Str::random(48);
        $contents = (string) file_get_contents((string) $file->getRealPath());

        Storage::disk($disk)->put($path, $contents);

        try {
            return DB::transaction(function () use ($application, $type, $file, $actor, $disk, $path, $contents): ApplicationDocument {
                $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

                if (! $locked->status->isEditableByApplicant()) {
                    throw DomainRuleViolation::notEditable();
                }

                if ($locked->status->isAwaitingReview() && ! $locked->examPeriod->isAcceptingApplications()) {
                    throw DomainRuleViolation::periodClosed();
                }

                if (! $locked->examPeriod->documentTypes()->whereKey($type->id)->exists()) {
                    throw DomainRuleViolation::notEligible('document_type');
                }

                if ($locked->documents()->count() >= (int) config('examis.documents.max_per_application')) {
                    throw DomainRuleViolation::documentLimitReached();
                }

                $previous = $locked->currentDocuments()
                    ->where('document_type_id', $type->id)
                    ->lockForUpdate()
                    ->first();

                $previous?->forceFill(['replaced_at' => now()])->save();

                $document = new ApplicationDocument;
                $document->forceFill([
                    'application_id' => $locked->id,
                    'document_type_id' => $type->id,
                    'uploaded_by' => $actor->id,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => Str::limit($this->safeOriginalName($file), 200, ''),
                    'mime_type' => (string) $file->getMimeType(),
                    'size_bytes' => strlen($contents),
                    'sha256' => hash('sha256', $contents),
                ])->save();

                $locked->events()->create([
                    'actor_id' => $actor->id,
                    'type' => $previous === null ? ApplicationEventType::DocumentUploaded : ApplicationEventType::DocumentReplaced,
                    'metadata' => array_filter([
                        'document' => $document->uuid,
                        'document_type' => $type->code,
                        'replaced' => $previous?->uuid,
                    ]),
                ]);

                $this->audit->record('document.uploaded', $document, ['application' => $locked->uuid], $actor);

                ScanApplicationDocument::dispatch($document->id)->afterCommit();

                return $document;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    /**
     * Keep the original name only for display: strip directories and control
     * characters. It is never used as a storage path.
     */
    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        return $name === '' ? 'document' : $name;
    }
}
