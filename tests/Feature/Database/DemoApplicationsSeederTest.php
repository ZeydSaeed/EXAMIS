<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\DemoApplicationsSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake((string) config('examis.documents.disk'));
    seedReferenceData();
    $this->seed([DocumentTypeSeeder::class, DemoSeeder::class]);
});

it('seeds 100 applicants covering every status and assignee option through the real actions', function () {
    $this->seed(DemoApplicationsSeeder::class);

    $applications = Application::query()->with('assignee')->get();

    expect(User::query()->where('username', 'like', 'demo.student%')->count())->toBe(DemoApplicationsSeeder::TOTAL)
        ->and($applications)->toHaveCount(DemoApplicationsSeeder::TOTAL)
        ->and($applications->pluck('status')->unique()->sortBy->value->values()->all())
        ->toEqualCanonicalizing(ApplicationStatus::cases())
        ->and($applications->pluck('assignee.username')->unique()->filter()->sort()->values()->all())
        ->toBe(['reviewer', 'supervisor'])
        ->and($applications->where('status', '!=', ApplicationStatus::Draft)->whereNull('assigned_to'))->not->toBeEmpty()
        ->and($applications->pluck('governorate_code')->unique())->toHaveCount(18)
        ->and($applications->pluck('branch_code')->unique())->toHaveCount(4)
        ->and($applications->pluck('category_code')->unique())->toHaveCount(3);

    $submitted = $applications->whereNotNull('submitted_at');
    expect($submitted->pluck('submitted_at')->map->toDateTimeString()->unique())->toHaveCount($submitted->count())
        ->and($submitted->every(fn (Application $application): bool => $application->submitted_at->isPast()))->toBeTrue();

    $reopened = Application::query()
        ->whereHas('events', fn ($query) => $query->where('from_status', ApplicationStatus::Accepted))
        ->count();
    expect($reopened)->toBe(5);
});

it('skips applicants that already exist when run again', function () {
    $this->seed(DemoApplicationsSeeder::class);
    $this->seed(DemoApplicationsSeeder::class);

    expect(Application::query()->count())->toBe(DemoApplicationsSeeder::TOTAL);
});
