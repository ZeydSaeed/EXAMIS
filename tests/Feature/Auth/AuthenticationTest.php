<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

function unenrolledStaff(array $attributes = []): User
{
    return User::factory()->staff(Role::Reviewer)->withoutTwoFactor()->create($attributes);
}

test('the login path leads to the staff sign-in page', function () {
    $this->get(route('login'))->assertRedirect(route('staff.login'));
});

test('staff can authenticate by email', function () {
    $user = unenrolledStaff();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('admin.home'));
});

test('staff with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->staff(Role::Reviewer)->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('staff can not authenticate with invalid password', function () {
    $user = unenrolledStaff();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('the username no longer signs anyone in', function () {
    $user = unenrolledStaff();

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a student account can not use the staff sign-in, even with a password', function () {
    $student = User::factory()->create(['email' => 'family@example.com']);

    $this->post(route('login.store'), ['email' => $student->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('student.logout'));

    $response->assertRedirect(route('start'));

    $this->assertGuest();
});

test('sign-in is rate limited', function () {
    $user = unenrolledStaff();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('deactivated staff receive the same error as a wrong password', function () {
    $user = User::factory()->staff(Role::Reviewer)->withoutTwoFactor()->deactivated()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email' => __('auth.failed')]);
});

test('an unknown email receives the same error as a wrong password', function () {
    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
});

test('emails are matched case-insensitively', function () {
    $user = unenrolledStaff(['email' => 'ali.hasan@example.com']);

    $this->post(route('login.store'), [
        'email' => 'Ali.Hasan@Example.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('a staff email belongs to one staff account, in any letter case', function () {
    unenrolledStaff(['email' => 'shared@example.com']);

    expect(fn () => unenrolledStaff(['email' => 'SHARED@example.com']))->toThrow(QueryException::class);
});

test('students may still share an email with each other and with staff', function () {
    unenrolledStaff(['email' => 'shared@example.com']);

    User::factory()->count(2)->create(['email' => 'shared@example.com']);

    expect(User::query()->where('email', 'shared@example.com')->count())->toBe(3);
});
