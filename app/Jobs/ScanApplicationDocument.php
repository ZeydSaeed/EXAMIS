<?php

namespace App\Jobs;

use App\Enums\ApplicationEventType;
use App\Enums\DocumentStatus;
use App\Models\ApplicationDocument;
use App\Services\Documents\DocumentScanner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Verifies a stored document before it becomes downloadable. Safe to retry:
 * only documents still pending are processed, under a row lock.
 */
class ScanApplicationDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public int $documentId)
    {
        $this->onQueue((string) config('examis.documents.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(DocumentScanner $scanner): void
    {
        $document = ApplicationDocument::query()->find($this->documentId);

        if ($document === null || $document->status !== DocumentStatus::Pending) {
            return;
        }

        $result = $scanner->scan(Storage::disk($document->disk)->get($document->path) ?? '', $document->mime_type);

        DB::transaction(function () use ($document, $result): void {
            $locked = ApplicationDocument::query()->whereKey($document->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== DocumentStatus::Pending) {
                return;
            }

            $locked->forceFill([
                'status' => $result->clean ? DocumentStatus::Verified : DocumentStatus::Rejected,
                'rejection_reason' => $result->reason,
                'scanned_at' => now(),
            ])->save();

            if (! $result->clean) {
                $locked->application->events()->create([
                    'type' => ApplicationEventType::DocumentRejected,
                    'metadata' => ['document' => $locked->uuid, 'reason' => $result->reason],
                ]);
            }
        });

        if (! $result->clean) {
            Storage::disk($document->disk)->delete($document->path);
            Log::warning('Application document rejected by scanner.', ['document' => $document->uuid, 'reason' => $result->reason]);
        }
    }
}
