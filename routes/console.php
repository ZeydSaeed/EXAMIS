<?php

use App\Actions\Students\DeleteStudent;
use App\Enums\DocumentStatus;
use App\Jobs\ScanApplicationDocument;
use App\Models\ApplicationDocument;
use App\Services\Staff\CloudflareAccess;
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

/*
 * Rewrites the Cloudflare Access list of the staff portal from the active
 * staff accounts, now and in this process (the hourly run also catches edits
 * made outside the Users page, such as the database screen).
 */
Artisan::command('examis:sync-staff-access', function (CloudflareAccess $access): int {
    if (! $access->isConfigured()) {
        $this->warn('Cloudflare Access is not configured (CLOUDFLARE_ACCESS_TOKEN); nothing to do.');

        return 0;
    }

    try {
        $count = $access->sync();
    } catch (Throwable $exception) {
        $access->remember(false);
        $this->error('Cloudflare Access sync failed: '.$exception->getMessage());

        return 1;
    }

    $this->info("Cloudflare Access now allows {$count} staff e-mail(s).");

    return 0;
})->purpose('Send the active staff e-mails to Cloudflare Access');

// Healthchecks.io hears from these two after every run (config/services.php).
$ping = fn (string $check): string => (string) config("services.healthchecks.{$check}");

Schedule::command('examis:sync-staff-access')->hourly()->withoutOverlapping()->onOneServer()
    ->pingOnSuccessIf($ping('staff_access_sync') !== '', $ping('staff_access_sync'))
    ->pingOnFailureIf($ping('staff_access_sync') !== '', $ping('staff_access_sync').'/fail');
Schedule::command('examis:rescan-pending')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('examis:verify-integrity')->dailyAt('02:30')->withoutOverlapping()->onOneServer()
    ->pingOnSuccessIf($ping('verify_integrity') !== '', $ping('verify_integrity'))
    ->pingOnFailureIf($ping('verify_integrity') !== '', $ping('verify_integrity').'/fail');
Schedule::command('queue:prune-failed --hours=720')->daily()->onOneServer();
Schedule::command('examis:prune-deleted-students')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
