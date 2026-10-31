<?php

use App\Enums\AccountType;
use App\Http\Controllers\Public\ApplicationTermsController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Students\TrustedDevices;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());

    // Registration follows the application terms step.
    $this->withSession([ApplicationTermsController::SESSION_KEY => now()->toIso8601String()]);
});

test('registration screen can be rendered once the terms are accepted', function () {
    $this->get(route('register'))->assertOk();
});

test('a student registers with a mobile number only', function () {
    $response = $this->post(route('register.store'), [
        'phone' => '0770 123 4567',
        'terms' => '1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('apply.specialization', absolute: false));

    $user = User::query()->where('phone', '07701234567')->sole();

    expect($user->account_type)->toBe(AccountType::Applicant)
        ->and($user->username)->toBeNull()
        ->and($user->name)->toBe((string) $user->student_number)
        ->and($user->password)->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->public_id)->not->toBeNull()
        ->and($user->created_at)->not->toBeNull()
        ->and($user->student_number)->toBeGreaterThanOrEqual(10000)->toBeLessThanOrEqual(99999)
        ->and($user->access_code)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'student.registered')->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('the registration form ignores a submitted username', function () {
    $this->post(route('register.store'), [
        'username' => 'old.form',
        'phone' => '07701234567',
        'terms' => '1',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('phone', '07701234567')->sole()->username)->toBeNull();
});

test('the access code is shown once on the student card and stored in plain text', function () {
    $response = $this->post(route('register.store'), [
        'phone' => '07701234567',
        'terms' => '1',
    ]);

    $user = User::query()->where('phone', '07701234567')->sole();
    $response->assertInertiaFlash('toast.message', __('auth.registered', ['number' => $user->student_number]));

    $code = null;

    $this->get(route('student.card'))->assertInertia(function (Assert $page) use (&$code) {
        $page->component('applicant/student-card')
            ->where('accessCode', fn ($value) => is_string($value) && preg_match('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', $value) === 1)
            ->where('qr.url', fn (string $url) => str_contains($url, '/s/'))
            ->etc();
        $code = $page->toArray()['props']['accessCode'];
    });

    expect($user->fresh()->getRawOriginal('access_code'))->toBe($code);

    $this->get(route('student.card'))->assertInertia(fn (Assert $page) => $page->where('accessCode', null)->etc());
});

test('the registering browser is remembered when the student asks', function () {
    $this->post(route('register.store'), [
        'phone' => '07701234567',
        'trust_device' => '1',
        'terms' => '1',
    ])->assertCookie(TrustedDevices::COOKIE);

    expect(User::query()->where('phone', '07701234567')->sole()->trustedDevices()->count())->toBe(1);
});

test('a shared device is not remembered at registration', function () {
    $this->post(route('register.store'), [
        'phone' => '07701234568',
        'terms' => '1',
    ])->assertCookieMissing(TrustedDevices::COOKIE);

    expect(User::query()->where('phone', '07701234568')->sole()->trustedDevices()->count())->toBe(0);
});

test('several accounts can register with one mobile number, each with its own id', function () {
    $existing = User::factory()->student()->create(['phone' => '07701234567']);

    $this->post(route('register.store'), [
        'phone' => '0770-123-4567',
        'terms' => '1',
    ])->assertSessionHasNoErrors();

    $accounts = User::query()->where('phone', '07701234567')->get();

    expect($accounts)->toHaveCount(2)
        ->and($accounts->pluck('student_number')->unique())->toHaveCount(2)
        ->and($accounts->pluck('public_id')->unique())->toHaveCount(2)
        ->and(auth()->id())->not->toBe($existing->id);
});

test('registration rejects a malformed mobile number', function () {
    $this->post(route('register.store'), [
        'phone' => '123',
        'terms' => '1',
    ])->assertSessionHasErrors('phone');
});

test('arabic-indic digits and the 00 prefix are normalized', function () {
    $this->post(route('register.store'), [
        'phone' => '٠٠٩٦٤٧٧٠١٢٣٤٥٦٧',
        'terms' => '1',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('phone', '+9647701234567')->exists())->toBeTrue();
});

test('registration requires accepting the terms', function () {
    $this->post(route('register.store'), [
        'phone' => '07701234567',
    ])->assertSessionHasErrors('terms');
});

test('registration is rate limited', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post(route('register.store'), ['phone' => 'x'])->assertRedirect();
    }

    $this->post(route('register.store'), ['phone' => 'x'])->assertTooManyRequests();
});
