<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\Receipts\ReceiptRenderer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Traits\Localizable;

/**
 * Builds the sent application's form off the request, so sending answers at
 * once even when many students send together. The printed page asks for the
 * form straight away: whichever comes first builds it, the other waits for
 * that copy (ReceiptRenderer::cached), so it is built once.
 */
class WarmApplicationForm implements ShouldBeUnique, ShouldQueue
{
    use Localizable, Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public int $applicationId, public string $locale)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->applicationId;
    }

    public function handle(ReceiptRenderer $renderer): void
    {
        $application = Application::query()->find($this->applicationId);

        if ($application === null) {
            return;
        }

        // The kept copy is per language: build the one the student will open.
        $this->withLocale($this->locale, fn () => $renderer->warm($application));
    }
}
