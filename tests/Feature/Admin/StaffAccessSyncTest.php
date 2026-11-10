<?php

use App\Enums\Role;
use App\Jobs\SyncStaffAccess;
use App\Models\User;
use App\Services\Staff\CloudflareAccess;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

const ACCESS_API = 'https://api.cloudflare.com/client/v4/accounts/acc123/access/';

function configureCloudflareAccess(): void
{
    config(['services.cloudflare_access' => [
        'account_id' => 'acc123',
        'token' => 'test-token',
        'domain' => 'staff.example.test',
        'policy' => 'EXAMIS staff e-mails',
        'always_allow' => ['Owner@Example.com'],
    ]]);
}

function fakeCloudflareAccess(): void
{
    Http::fake([
        ACCESS_API.'apps' => Http::response(['success' => true, 'result' => [
            ['id' => 'other-app', 'domain' => 'students.example.test'],
            ['id' => 'app1', 'domain' => 'staff.example.test'],
        ]]),
        ACCESS_API.'apps/app1/policies' => Http::response(['success' => true, 'result' => [
            ['id' => 'pol1', 'name' => 'EXAMIS staff e-mails', 'precedence' => 1],
        ]]),
        ACCESS_API.'apps/app1/policies/pol1' => Http::response(['success' => true, 'result' => []]),
    ]);
}

/**
 * @return list<string> the e-mails the last policy update sent
 */
function sentAccessEmails(): array
{
    $put = Http::recorded(fn (HttpRequest $request): bool => $request->method() === 'PUT')->last();

    return array_map(fn (array $rule): string => $rule['email']['email'], $put[0]['include']);
}

it('sends exactly the active staff e-mails and the always-allowed ones', function () {
    configureCloudflareAccess();
    fakeCloudflareAccess();

    User::factory()->staff(Role::Reviewer)->create(['email' => 'Reviewer@Example.com']);
    User::factory()->staff(Role::Auditor)->deactivated()->create(['email' => 'gone@example.com']);
    User::factory()->student()->create(['email' => 'student@example.com']);

    $count = app(CloudflareAccess::class)->sync();

    expect(sentAccessEmails())->toBe(['owner@example.com', 'reviewer@example.com'])
        ->and($count)->toBe(2);

    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'PUT'
        && $request->url() === ACCESS_API.'apps/app1/policies/pol1'
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request['decision'] === 'allow'
        && $request['precedence'] === 1);

    expect(app(CloudflareAccess::class)->status())->toMatchArray(['ok' => true, 'count' => 2]);
});

it('never sends an empty list, which would lock everyone out', function () {
    configureCloudflareAccess();
    config(['services.cloudflare_access.always_allow' => []]);
    fakeCloudflareAccess();

    expect(fn () => app(CloudflareAccess::class)->sync())->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});

it('fails loudly when the Access application or its policy is missing', function () {
    configureCloudflareAccess();
    Http::fake([ACCESS_API.'apps' => Http::response(['success' => true, 'result' => []])]);

    expect(fn () => app(CloudflareAccess::class)->sync())->toThrow(RuntimeException::class, 'staff.example.test');

    Http::assertNotSent(fn (HttpRequest $request): bool => $request->method() === 'PUT');
});

it('does nothing without a token', function () {
    Http::fake();

    (new SyncStaffAccess)->handle(app(CloudflareAccess::class));

    Http::assertNothingSent();
    expect(app(CloudflareAccess::class)->status())->toBeNull();
});

it('queues a sync when an administrator adds a staff member', function () {
    configureCloudflareAccess();
    Queue::fake();

    $this->actingAs(staffWith(Role::Administrator))
        ->post(route('admin.users.store'), [
            'name' => 'موظف جديد',
            'email' => 'new@example.com',
            'phone' => '07701234567',
            'password' => 'Staff-Password-123!',
            'password_confirmation' => 'Staff-Password-123!',
            'roles' => ['reviewer'],
        ])
        ->assertSessionHasNoErrors();

    Queue::assertPushed(SyncStaffAccess::class);
    expect(app(CloudflareAccess::class)->status())->toMatchArray(['ok' => null]);
});

it('queues a sync when a staff e-mail changes or someone is deactivated, not for a name', function () {
    configureCloudflareAccess();
    Queue::fake();
    $admin = staffWith(Role::Administrator);
    $staff = staffWith(Role::Reviewer);

    $this->actingAs($admin)
        ->put(route('admin.users.account', $staff), ['name' => 'اسم آخر', 'email' => $staff->email])
        ->assertSessionHasNoErrors();
    Queue::assertNotPushed(SyncStaffAccess::class);

    $this->actingAs($admin)
        ->put(route('admin.users.account', $staff), ['name' => 'اسم آخر', 'email' => 'renamed@example.com'])
        ->assertSessionHasNoErrors();
    Queue::assertPushed(SyncStaffAccess::class, 1);

    // A worker picking the job up releases its lock, so the next change queues again.
    Cache::lock('laravel_unique_job:'.SyncStaffAccess::class.':staff-access')->forceRelease();

    $this->actingAs($admin)
        ->put(route('admin.users.status', $staff), ['active' => false])
        ->assertSessionHasNoErrors();
    Queue::assertPushed(SyncStaffAccess::class, 2);
});

it('shows the administrator whether the e-mails reached Cloudflare', function () {
    $admin = staffWith(Role::Administrator);

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('accessSync', null));

    configureCloudflareAccess();
    app(CloudflareAccess::class)->remember(false);

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('accessSync.ok', false));
});

it('syncs from the console', function () {
    configureCloudflareAccess();
    fakeCloudflareAccess();
    staffWith(Role::Administrator);

    $this->artisan('examis:sync-staff-access')->assertSuccessful();
    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'PUT');
});

it('reports a failed console sync with a non-zero exit', function () {
    configureCloudflareAccess();
    Http::fake([ACCESS_API.'*' => Http::response(['success' => false], 500)]);
    staffWith(Role::Administrator);

    $this->artisan('examis:sync-staff-access')->assertFailed();
    expect(app(CloudflareAccess::class)->status())->toMatchArray(['ok' => false]);
});
