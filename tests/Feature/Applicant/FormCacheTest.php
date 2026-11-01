<?php

use App\Models\Application;
use App\Services\Receipts\ReceiptRenderer;
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

it('builds the form as the application is sent', function () {
    $application = readyDraft();

    $this->actingAs($application->user)
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertRedirect(route('applications.printed', $application));

    expect(keptForms($application))->toHaveCount(1);
});
