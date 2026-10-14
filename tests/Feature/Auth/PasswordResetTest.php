<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested by username', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['username' => $user->username])
        ->assertSessionHas('status', __('auth.reset_link_generic'));

    Notification::assertSentTo($user, ResetPassword::class);
});

test('an unknown username gets the same response as a real one', function () {
    Notification::fake();

    $this->post(route('password.email'), ['username' => 'nobody.here'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('auth.reset_link_generic'));

    Notification::assertNothingSent();
});

test('a reset link reaches only the requested account when an email is shared', function () {
    Notification::fake();

    $requester = User::factory()->create(['email' => 'family@example.com']);
    $sibling = User::factory()->create(['email' => 'family@example.com']);

    $this->post(route('password.email'), ['username' => $requester->username]);

    Notification::assertSentTo($requester, ResetPassword::class);
    Notification::assertNotSentTo($sibling, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['username' => $user->username]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['username' => $user->username]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'username' => $user->username,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();

        return true;
    });
});

test('a reset token cannot change the password of another account sharing the email', function () {
    Notification::fake();

    $requester = User::factory()->create(['email' => 'family@example.com']);
    $sibling = User::factory()->create(['email' => 'family@example.com']);

    $this->post(route('password.email'), ['username' => $requester->username]);

    Notification::assertSentTo($requester, ResetPassword::class, function ($notification) use ($sibling) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'username' => $sibling->username,
            'password' => 'hijacked-password',
            'password_confirmation' => 'hijacked-password',
        ])->assertSessionHasErrors('username');

        expect(Hash::check('hijacked-password', $sibling->fresh()->password))->toBeFalse();

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'username' => $user->username,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('username');
});

test('reset link requests are rate limited', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post(route('password.email'), ['username' => 'someone']);
    }

    $this->post(route('password.email'), ['username' => 'someone'])->assertTooManyRequests();
});
