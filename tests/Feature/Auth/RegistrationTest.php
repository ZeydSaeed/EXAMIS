<?php

use App\Enums\AccountType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'username' => 'test.user',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    expect(User::query()->where('username', 'test.user')->sole()->account_type)->toBe(AccountType::Applicant);
});

test('a new applicant goes straight to the dashboard and is shown their username', function () {
    $this->post(route('register.store'), [
        'username' => 'new.student',
        'name' => 'New Student',
        'email' => 'student@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ])->assertRedirect(route('dashboard', absolute: false))
        ->assertInertiaFlash('toast.message', __('auth.registered', ['username' => 'new.student']));

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->component('applicant/dashboard'));
});

test('several accounts may share one email address', function () {
    User::factory()->create(['email' => 'family@example.com']);

    $this->post(route('register.store'), [
        'username' => 'second.child',
        'name' => 'Second Child',
        'email' => 'family@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'family@example.com')->count())->toBe(2);
});

test('registration rejects a username that is already taken', function () {
    User::factory()->create(['username' => 'taken.name']);

    $this->post(route('register.store'), [
        'username' => 'Taken.Name',
        'name' => 'Someone Else',
        'email' => 'other@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('registration requires accepting the terms', function () {
    $this->post(route('register.store'), [
        'username' => 'no.terms',
        'name' => 'No Terms',
        'email' => 'no-terms@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('terms');
});

test('registration is rate limited', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post(route('register.store'), ['username' => 'x'])->assertRedirect();
    }

    $this->post(route('register.store'), ['username' => 'x'])->assertTooManyRequests();
});
