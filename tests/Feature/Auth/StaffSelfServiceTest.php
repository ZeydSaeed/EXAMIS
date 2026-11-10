<?php

use App\Enums\Role;
use App\Models\Application;
use App\Models\ApplicationDocument;

it('signs staff in with an email and a password alone', function () {
    $staff = staffWith(Role::Administrator);

    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('admin.home'));

    $this->assertAuthenticatedAs($staff);
    $this->get(route('admin.users.index'))->assertOk();
});

it('has no two-factor endpoints left', function (string $method, string $path) {
    $this->call($method, 'http://staff.examis.test'.$path)->assertNotFound();
})->with([
    ['GET', '/two-factor-challenge'],
    ['POST', '/two-factor-challenge'],
    ['POST', '/user/two-factor-authentication'],
    ['DELETE', '/user/two-factor-authentication'],
    ['GET', '/user/two-factor-qr-code'],
    ['GET', '/user/two-factor-recovery-codes'],
]);

it('never remembers a staff sign-in', function () {
    $staff = staffWith(Role::Reviewer);
    $token = $staff->remember_token;

    $response = $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password', 'remember' => 'on']);

    $this->assertAuthenticatedAs($staff);
    expect($staff->fresh()->remember_token)->toBe($token)
        ->and(collect($response->headers->getCookies())->filter(fn ($cookie): bool => str_starts_with($cookie->getName(), 'remember_')))->toBeEmpty();
});

it('keeps documents and forms behind a staff sign-in', function () {
    $document = ApplicationDocument::factory()->verified()->create();
    $application = Application::factory()->submitted()->create();

    $this->get(route('admin.documents.show', $document))->assertRedirect(route('staff.login'));
    $this->get(route('admin.applications.receipt', $application))->assertRedirect(route('staff.login'));
});

it('stops staff changing their own roles in any direction', function () {
    $admin = staffWith(Role::Administrator);

    $this->actingAs($admin)
        ->put(route('admin.users.roles', $admin), ['roles' => ['administrator', 'supervisor']])
        ->assertSessionHasErrors('roles');

    expect($admin->fresh()->roles())->toBe([Role::Administrator]);
});

it('only changes the status of staff accounts', function () {
    $student = applicant();

    $this->actingAs(staffWith(Role::Administrator))
        ->put(route('admin.users.status', $student), ['active' => false])
        ->assertSessionHasErrors();

    expect($student->fresh()->deactivated_at)->toBeNull();
});
