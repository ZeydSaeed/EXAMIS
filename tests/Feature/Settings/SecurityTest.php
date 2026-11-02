<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('security page is displayed', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('settings/security')->has('passwordRules'));
});

test('security page requires password confirmation', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});

test('password can be updated', function () {
    $user = User::factory()->staff()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'New-Password-123!',
            'password_confirmation' => 'New-Password-123!',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('New-Password-123!', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->staff()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'New-Password-123!',
            'password_confirmation' => 'New-Password-123!',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});
