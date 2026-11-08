<?php

use App\Actions\Students\DeleteStudent;
use App\Enums\DocumentStatus;
use App\Jobs\ScanApplicationDocument;
use App\Models\ApplicationDocument;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Re-queue documents whose scan job was lost (worker crash, Redis flush).
 * Safe to repeat: the job is unique per document and ignores non-pending rows.
 */
Artisan::command('examis:rescan-pending {--older-than=10 : Minutes a document may stay pending}', function (): void {
    $count = 0;

    ApplicationDocument::query()
        ->where('status', DocumentStatus::Pending)
        ->whereNull('replaced_at')
        ->where('created_at', '<', now()->subMinutes((int) $this->option('older-than')))
        ->select('id')
        ->lazyById(200)
        ->each(function (ApplicationDocument $document) use (&$count): void {
            ScanApplicationDocument::dispatch($document->id);
            $count++;
        });

    $this->info("Re-queued {$count} pending document scan(s).");
})->purpose('Re-queue document scans that have been pending too long');

/*
 * Snapshots taken before a student is deleted (DeleteStudent) hold personal
 * data, so they are kept only long enough to undo a mistake.
 */
Artisan::command('examis:prune-deleted-students {--days=30 : Days a snapshot is kept}', function (): void {
    $disk = Storage::disk('local');
    $cutoff = now()->subDays((int) $this->option('days'))->getTimestamp();
    $count = 0;

    foreach ($disk->directories(DeleteStudent::SNAPSHOT_DIRECTORY) as $directory) {
        if ($disk->lastModified($directory.'/rows.json') < $cutoff) {
            $disk->deleteDirectory($directory);
            $count++;
        }
    }

    $this->info("Removed {$count} deleted-student snapshot(s).");
})->purpose('Remove deleted-student snapshots older than the retention period');

Schedule::command('examis:rescan-pending')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('examis:verify-integrity')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily()->onOneServer();
Schedule::command('examis:prune-deleted-students')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
