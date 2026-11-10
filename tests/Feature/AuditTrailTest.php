<?php

use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Str;

it('records successful and failed sign-ins without storing what was typed', function () {
    $user = User::factory()->staff()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    // Only this attempt: the database may be shared with a running site.
    $hash = substr(hash_hmac('sha256', Str::lower($user->email), (string) config('app.key')), 0, 32);
    $failed = AuditEvent::query()->where('action', 'auth.failed')->get()
        ->filter(fn (AuditEvent $event): bool => $event->metadata['email_hash'] === $hash)
        ->sole();
    expect(json_encode($failed->metadata))->not->toContain($user->email)
        ->and(json_encode($failed->metadata))->not->toContain('wrong-password')
        ->and($failed->metadata['email_hash'])->toHaveLength(32)
        ->and(AuditEvent::query()->where('action', 'auth.login')->where('actor_id', $user->id)->exists())->toBeTrue();
});

it('refuses to modify audit events', function () {
    $event = AuditEvent::query()->create(['action' => 'test.event']);

    expect(fn () => $event->update(['action' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

it('refuses to modify application history', function () {
    $application = Application::factory()->create();
    $event = $application->events()->create(['type' => 'created']);

    expect(fn () => $event->update(['reason' => 'rewritten']))->toThrow(LogicException::class)
        ->and(fn () => ApplicationEvent::query()->whereKey($event->id)->sole()->delete())->toThrow(LogicException::class);
});

it('tags audit events with the request id', function () {
    $user = User::factory()->staff()->create();

    $this->withHeader('X-Request-Id', 'req-1234567890')
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertHeader('X-Request-Id', 'req-1234567890');

    expect(AuditEvent::query()->where('action', 'auth.login')->sole()->request_id)->toBe('req-1234567890');
});
