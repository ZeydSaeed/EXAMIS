<?php

use App\Enums\Role;
use App\Models\Application;

/*
 * Inputs the ZAP active scan sent on perf (2026-11-07) that answered 500.
 */

beforeEach(function () {
    seedReferenceData();
});

it('refuses a null byte in a date filter instead of failing', function () {
    $this->actingAs(staffWith(Role::Supervisor))
        ->get(route('admin.student-export.index', ['from' => "2026-01-01\0<script>", 'to' => '2026-12-31']))
        ->assertStatus(400);
});

it('refuses a null byte in a form field instead of failing', function () {
    $application = Application::factory()->create();

    $this->actingAs($application->user)
        ->put(route('applications.update', $application), ['lock_version' => 1, 'birth_date' => "2006-05-20\0"])
        ->assertStatus(400);

    expect($application->fresh()->lock_version)->toBe(1);
});

it('answers 404 for an application or document key that is not a UUID', function (string $route, string $parameter) {
    $application = Application::factory()->create();
    $user = $route === 'applications.show' ? $application->user : staffWith(Role::Supervisor);

    $this->actingAs($user)
        ->get(route($route, [$parameter => '597592337783201897']))
        ->assertNotFound();
})->with([
    ['applications.show', 'application'],
    ['admin.applications.show', 'application'],
    ['admin.documents.show', 'document'],
]);

it('still opens an application by its UUID', function () {
    $application = Application::factory()->create();

    $this->actingAs($application->user)
        ->get(route('applications.show', $application))
        ->assertSuccessful();
});

it('lets ordinary text through', function () {
    $this->actingAs(staffWith(Role::Supervisor))
        ->get(route('admin.student-export.index', ['from' => '2026-01-01', 'to' => '2026-12-31']))
        ->assertSuccessful();
});
