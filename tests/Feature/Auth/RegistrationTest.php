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

test('a student registers with a username and a mobile number only', function () {
    $response = $this->post(route('register.store'), [
        'username' => 'test.user',
        'phone' => '0770 123 4567',
        'terms' => '1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('apply.specialization', absolute: false));

    $user = User::query()->where('username', 'test.user')->sole();

    expect($user->account_type)->toBe(AccountType::Applicant)
        ->and($user->phone)->toBe('07701234567')
        ->and($user->password)->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->public_id)->not->toBeNull()
        ->and($user->student_number)->toBeGreaterThanOrEqual(10000)->toBeLessThanOrEqual(99999)
        ->and($user->access_code)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'student.registered')->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('the access code is shown once on the student card and stored in plain text', function () {
    $this->post(route('register.store'), [
        'username' => 'new.student',
        'phone' => '07701234567',
        'terms' => '1',
    ])->assertInertiaFlash('toast.message', __('auth.registered', ['username' => 'new.student']));

    $code = null;

    $this->get(route('student.card'))->assertInertia(function (Assert $page) use (&$code) {
        $page->component('applicant/student-card')
            ->where('accessCode', fn ($value) => is_string($value) && preg_match('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', $value) === 1)
            ->where('qr.url', fn (string $url) => str_contains($url, '/s/'))
            ->etc();
        $code = $page->toArray()['props']['accessCode'];
    });

    $user = User::query()->where('username', 'new.student')->sole();
    expect($user->getRawOriginal('access_code'))->toBe($code);

    $this->get(route('student.card'))->assertInertia(fn (Assert $page) => $page->where('accessCode', null)->etc());
});

test('the registering browser is remembered when the student asks', function () {
    $this->post(route('register.store'), [
        'username' => 'own.device',
        'phone' => '07701234567',
        'trust_device' => '1',
        'terms' => '1',
    ])->assertCookie(TrustedDevices::COOKIE);

    expect(User::query()->where('username', 'own.device')->sole()->trustedDevices()->count())->toBe(1);
});

test('a shared device is not remembered at registration', function () {
    $this->post(route('register.store'), [
        'username' => 'cafe.device',
        'phone' => '07701234568',
        'terms' => '1',
    ])->assertCookieMissing(TrustedDevices::COOKIE);

    expect(User::query()->where('username', 'cafe.device')->sole()->trustedDevices()->count())->toBe(0);
});

test('registration rejects a username that is already taken', function () {
    User::factory()->create(['username' => 'taken.name']);

    $this->post(route('register.store'), [
        'username' => 'Taken.Name',
        'phone' => '07701234567',
        'terms' => '1',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('registration rejects a mobile number registered to another account', function () {
    User::factory()->student()->create(['phone' => '07701234567']);

    $this->post(route('register.store'), [
        'username' => 'second.account',
        'phone' => '0770-123-4567',
        'terms' => '1',
    ])->assertSessionHasErrors(['phone' => __('validation.custom.phone.unique')]);

    $this->assertGuest();
});

test('registration accepts Arabic and English usernames of three or more letters', function (string $username) {
    $this->post(route('register.store'), [
        'username' => $username,
        'phone' => '07701234567',
        'terms' => '1',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('username', $username)->exists())->toBeTrue();
})->with(['علي', 'سارة.حسين', 'ali']);

test('registration rejects usernames shorter than three letters or with other characters', function (string $username) {
    $this->post(route('register.store'), [
        'username' => $username,
        'phone' => '07701234567',
        'terms' => '1',
    ])->assertSessionHasErrors('username');
})->with(['عل', 'ab', 'علي حسن', 'ali@x']);

test('registration rejects usernames made of digits only', function () {
    $this->post(route('register.store'), [
        'username' => '12345',
        'phone' => '07701234567',
        'terms' => '1',
    ])->assertSessionHasErrors('username');
});

test('registration rejects a malformed mobile number', function () {
    $this->post(route('register.store'), [
        'username' => 'bad.phone',
        'phone' => '123',
        'terms' => '1',
    ])->assertSessionHasErrors('phone');
});

test('arabic-indic digits and the 00 prefix are normalized', function () {
    $this->post(route('register.store'), [
        'username' => 'arabic.digits',
        'phone' => '٠٠٩٦٤٧٧٠١٢٣٤٥٦٧',
        'terms' => '1',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('username', 'arabic.digits')->sole()->phone)->toBe('+9647701234567');
});

test('registration requires accepting the terms', function () {
    $this->post(route('register.store'), [
        'username' => 'no.terms',
        'phone' => '07701234567',
    ])->assertSessionHasErrors('terms');
});

test('registration is rate limited', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post(route('register.store'), ['username' => 'x'])->assertRedirect();
    }

    $this->post(route('register.store'), ['username' => 'x'])->assertTooManyRequests();
});
