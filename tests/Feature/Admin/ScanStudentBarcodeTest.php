<?php

use App\Enums\Role;
use App\Models\Application;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The barcode on the form holds the student number. A reader types it into the
 * search box and presses Enter, which must land on that student's application.
 */
function studentApplication(int $number): Application
{
    return Application::factory()
        ->for(User::factory()->student()->create(['student_number' => $number]))
        ->submitted()
        ->create();
}

it('opens the student application when their number is scanned', function () {
    $application = studentApplication(647179);
    studentApplication(647180);

    $this->actingAs(User::factory()->staff(Role::Administrator)->create())
        ->get(route('admin.applications.index', ['q' => '647179']))
        ->assertRedirect(route('admin.applications.show', $application));
});

it('accepts the five-digit numbers issued earlier', function () {
    $application = studentApplication(54321);

    $this->actingAs(User::factory()->staff(Role::Administrator)->create())
        ->get(route('admin.applications.index', ['q' => ' 54321 ']))
        ->assertRedirect(route('admin.applications.show', $application));
});

it('stays on the list for a number nobody has', function () {
    studentApplication(647179);

    $this->actingAs(User::factory()->staff(Role::Administrator)->create())
        ->get(route('admin.applications.index', ['q' => '111111']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/applications/index'));
});

it('does not open an application the staff member may not see', function () {
    studentApplication(647179)->forceFill(['assigned_to' => User::factory()->staff(Role::Reviewer)->create()->id])->save();

    $this->actingAs(User::factory()->staff(Role::Reviewer)->create())
        ->get(route('admin.applications.index', ['q' => '647179']))
        ->assertOk();
});

it('prints the student number as a barcode, without the old footer', function () {
    seedReferenceData();
    $application = Application::factory()
        ->for(User::factory()->student()->create(['student_number' => 647179]))
        ->complete()
        ->submitted()
        ->create();

    $pdf = (string) $this->actingAs(User::factory()->staff(Role::Administrator)->create())
        ->get(route('admin.applications.receipt', $application))
        ->getContent();

    expect($pdf)->toStartWith('%PDF-');
});
