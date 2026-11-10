<?php

namespace App\Jobs;

use App\Services\Staff\CloudflareAccess;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the active staff e-mails to Cloudflare Access after an administrator
 * adds a staff member, changes an e-mail or (de)activates someone. One sync
 * covers every change made before it starts, so a burst of changes queues one
 * job; the lock is released when it starts, so a change made while it runs
 * queues the next. Retries for a while when Cloudflare is unreachable; the
 * hourly schedule catches anything that still slips through.
 */
class SyncStaffAccess implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 60;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'staff-access';
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(CloudflareAccess $access): void
    {
        if (! $access->isConfigured()) {
            return;
        }

        $access->sync();
    }

    public function failed(?Throwable $exception): void
    {
        app(CloudflareAccess::class)->remember(false);

        Log::error('Cloudflare Access staff sync failed after every retry.', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
