<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->staff()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->staff()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
});

test('nobody can delete their own account', function () {
    foreach ([User::factory()->staff()->create(), User::factory()->create()] as $user) {
        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertMethodNotAllowed();

        expect($user->fresh())->not->toBeNull();
    }
});
