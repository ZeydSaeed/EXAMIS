<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('apply.terms'));
});

test('a student never stays on the dashboard', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('apply.specialization'));
});

test('a student with a draft goes to the form', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->create();

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertRedirect(route('applications.edit', $application));
});

test('a student who has sent the application goes to the final page', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->submitted()->create();

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertRedirect(route('applications.printed', $application));
});

test('an application waiting for a correction goes to the form', function () {
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->submitted()->create();
    $application->forceFill(['status' => ApplicationStatus::RequiresCorrection])->save();

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertRedirect(route('applications.edit', $application));
});
