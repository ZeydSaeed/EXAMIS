<?php

use App\Enums\DocumentStatus;
use App\Jobs\ScanApplicationDocument;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('passes on a consistent database and storage', function () {
    $application = readyDraft();
    $this->actingAs($application->user)->post(route('applications.submit', $application), ['declaration' => '1']);

    $this->artisan('examis:verify-integrity', ['--hashes' => true])
        ->expectsOutputToContain('All integrity checks passed.')
        ->assertSuccessful();
});

it('fails when a referenced document is missing from storage', function () {
    $document = ApplicationDocument::factory()->verified()->create();
    Storage::disk('documents')->delete($document->path);

    $this->artisan('examis:verify-integrity')
        ->expectsOutputToContain('missing from storage')
        ->assertFailed();
});

it('detects tampered document contents', function () {
    $document = ApplicationDocument::factory()->verified()->create();
    Storage::disk('documents')->put($document->path, 'tampered');

    $this->artisan('examis:verify-integrity', ['--hashes' => true])
        ->expectsOutputToContain('no longer matches the recorded SHA-256')
        ->assertFailed();
});

it('detects a numbering counter that would reissue existing references', function () {
    $application = readyDraft();
    $this->actingAs($application->user)->post(route('applications.submit', $application), ['declaration' => '1']);
    $application->examPeriod->forceFill(['last_sequence' => 0])->save();

    $this->artisan('examis:verify-integrity')
        ->expectsOutputToContain('Numbering counter behind')
        ->assertFailed();
});

it('re-queues documents stuck in scanning', function () {
    Queue::fake();
    $stuck = ApplicationDocument::factory()->create(['status' => DocumentStatus::Pending, 'created_at' => now()->subHour()]);
    ApplicationDocument::factory()->create(['status' => DocumentStatus::Pending]);
    ApplicationDocument::factory()->verified()->create(['created_at' => now()->subHour()]);

    $this->artisan('examis:rescan-pending')->assertSuccessful();

    Queue::assertPushed(ScanApplicationDocument::class, 1);
    Queue::assertPushed(ScanApplicationDocument::class, fn ($job) => $job->documentId === $stuck->id);
});

it('ignores applications that were never submitted', function () {
    Application::factory()->complete()->create();

    $this->artisan('examis:verify-integrity')->assertSuccessful();
});
