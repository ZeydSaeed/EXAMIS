<?php

use App\Jobs\WarmApplicationForm;
use App\Models\Application;
use App\Services\Receipts\ReceiptRenderer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Building the form takes about a second, so a copy is kept until something it
 * shows changes. A stale copy would print wrong data, hence these checks.
 */
beforeEach(function () {
    seedReferenceData();
    Storage::fake('local');
    config(['examis.documents.form_cache' => true]);
});

function keptForms(Application $application): array
{
    return Storage::disk('local')->files('form-cache/'.$application->uuid);
}

it('keeps a copy and serves it again', function () {
    $application = Application::factory()->complete()->submitted()->create();
    $renderer = app(ReceiptRenderer::class);

    $first = $renderer->cached($application);
    $kept = keptForms($application);

    expect($first)->toStartWith('%PDF-')->and($kept)->toHaveCount(1);

    Storage::disk('local')->put($kept[0], 'the kept copy');

    expect($renderer->cached($application->fresh()))->toBe('the kept copy');
});

it('rebuilds when the application changes and drops the old copy', function () {
    $application = Application::factory()->complete()->submitted()->create();
    $renderer = app(ReceiptRenderer::class);

    $renderer->cached($application);
    $old = keptForms($application);

    $this->travel(1)->minutes();
    $application->update(['district' => 'حي جديد']);

    $renderer->cached($application->fresh());

    expect(keptForms($application))->toHaveCount(1)
        ->and(keptForms($application))->not->toBe($old);
});

it('keeps nothing on a public disk', function () {
    $application = Application::factory()->complete()->submitted()->create();

    app(ReceiptRenderer::class)->cached($application);

    expect(Storage::disk('local')->path(keptForms($application)[0]))->not->toContain('public');
});

it('builds the form on the queue as the application is sent, not inside the request', function () {
    Queue::fake();
    $application = readyDraft();

    $this->actingAs($application->user)
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertRedirect(route('applications.printed', $application));

    expect(keptForms($application))->toBeEmpty();
    Queue::assertPushed(WarmApplicationForm::class, fn (WarmApplicationForm $job): bool => $job->applicationId === $application->id && $job->locale === app()->getLocale());
});

it('keeps the copy the queued job builds, in the language the student used', function () {
    $application = Application::factory()->complete()->submitted()->create();

    (new WarmApplicationForm($application->id, 'en'))->handle(app(ReceiptRenderer::class));
    $kept = keptForms($application);

    expect($kept)->toHaveCount(1)
        ->and(app()->getLocale())->not->toBe('en');

    // The student's own view in that language reads the kept copy.
    Storage::disk('local')->put($kept[0], 'the kept copy');
    app()->setLocale('en');

    expect(app(ReceiptRenderer::class)->cached($application->fresh()))->toBe('the kept copy');
});

it('skips an application that no longer exists', function () {
    (new WarmApplicationForm(999_999, 'ar'))->handle(app(ReceiptRenderer::class));

    expect(Storage::disk('local')->allFiles('form-cache'))->toBeEmpty();
});
