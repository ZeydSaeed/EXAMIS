<?php

use App\Enums\DocumentStatus;
use App\Jobs\ScanApplicationDocument;
use App\Models\ApplicationDocument;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

Schedule::command('examis:rescan-pending')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('examis:verify-integrity')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
Schedule::command('auth:clear-resets')->hourly()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily()->onOneServer();
