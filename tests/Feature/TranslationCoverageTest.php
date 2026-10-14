<?php

use App\Enums\DocumentStatus;
use App\Enums\ExamPeriodStatus;
use Illuminate\Support\Arr;
use Symfony\Component\Finder\Finder;

/**
 * Every static t('…') key used by the React frontend must exist in both the
 * Arabic and English dictionaries, and both dictionaries must have the same
 * keys, so no screen ever shows a raw key in either language.
 */
function frontendTranslationKeys(): array
{
    $keys = [];

    foreach (Finder::create()->files()->in(resource_path('js'))->name(['*.tsx', '*.ts']) as $file) {
        preg_match_all("/\\bt\\(\\s*'([a-z_]+(?:\\.[a-z0-9_]+)+)'/", $file->getContents(), $matches);
        array_push($keys, ...$matches[1]);
    }

    return array_values(array_unique($keys));
}

test('the Arabic and English dictionaries define the same keys', function () {
    $arabic = array_keys(Arr::dot(require lang_path('ar/ui.php')));
    $english = array_keys(Arr::dot(require lang_path('en/ui.php')));

    expect(array_values(array_diff($arabic, $english)))->toBe([])
        ->and(array_values(array_diff($english, $arabic)))->toBe([]);
});

test('every key used by the frontend is translated', function (string $locale) {
    $dictionary = Arr::dot(require lang_path("{$locale}/ui.php"));
    $used = frontendTranslationKeys();
    $missing = array_values(array_filter($used, fn (string $key) => ! array_key_exists($key, $dictionary)));

    expect(count($used))->toBeGreaterThan(200)
        ->and($missing)->toBe([]);
})->with(['ar', 'en']);

test('dynamic keys built from enums are translated', function (string $locale) {
    $dictionary = Arr::dot(require lang_path("{$locale}/ui.php"));
    $dynamic = [
        ...array_map(fn ($step) => "applicant.steps.{$step}", ['personal', 'contact', 'education', 'documents', 'review']),
        ...array_map(fn ($status) => "documents.status.{$status->value}", DocumentStatus::cases()),
        ...array_map(fn ($status) => "periods.status.{$status->value}", ExamPeriodStatus::cases()),
        ...array_map(fn ($check) => "operations.check.{$check}", ['database', 'cache', 'storage']),
    ];

    expect(array_values(array_filter($dynamic, fn (string $key) => ! array_key_exists($key, $dictionary))))->toBe([]);
})->with(['ar', 'en']);
