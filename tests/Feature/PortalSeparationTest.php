<?php

use App\Enums\Portal;
use App\Enums\Role;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Session\EncryptedStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

function studentUrl(string $path): string
{
    return 'http://'.Portal::Students->host().$path;
}

function staffUrl(string $path): string
{
    return 'http://'.Portal::Staff->host().$path;
}

it('answers staff paths with 404 on the student portal', function (string $method, string $path) {
    $this->call($method, studentUrl($path))->assertNotFound();
})->with([
    ['GET', '/admin'],
    ['GET', '/admin/users'],
    ['GET', '/admin/documents/1'],
    ['GET', '/staff/login'],
    ['GET', '/staff/invitation'],
    ['GET', '/login'],
    ['POST', '/login'],
    ['GET', '/two-factor-challenge'],
    ['GET', '/settings/security'],
    ['POST', '/user/two-factor-authentication'],
]);

it('answers student paths with 404 on the staff portal', function (string $method, string $path) {
    $this->call($method, staffUrl($path))->assertNotFound();
})->with([
    ['GET', '/apply/terms'],
    ['GET', '/register'],
    ['POST', '/register'],
    ['GET', '/portal'],
    ['GET', '/track'],
    ['POST', '/student/login'],
    ['GET', '/dashboard'],
    ['GET', '/s/01966a1e-0000-7000-8000-000000000000'],
]);

it('serves nothing but health checks on any other host', function () {
    $this->get('http://other.test/')->assertNotFound();
    $this->get('http://other.test/admin')->assertNotFound();
    $this->get('http://other.test/up')->assertOk();
});

it('binds every named route to a portal', function () {
    $unbound = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => $route->getDomain() === null)
        ->map(fn (RoutingRoute $route): string => $route->uri())
        ->reject(fn (string $uri): bool => in_array($uri, ['up', 'health/ready'], true) || str_starts_with($uri, 'storage/'))
        ->values()
        ->all();

    expect($unbound)->toBe([]);
});

it('does not let a student session reach the staff portal', function () {
    $this->actingAs(applicant())
        ->get(staffUrl('/admin'))
        ->assertRedirect(route('staff.login'));

    expect(Auth::guard('staff')->check())->toBeFalse();
});

it('does not let a staff session reach the student area', function () {
    $this->actingAs(staffWith(Role::Administrator))
        ->get(studentUrl('/dashboard'))
        ->assertRedirect(route('apply.terms'));

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('never resolves an account through the other guard', function () {
    $student = applicant();
    $staff = staffWith(Role::Reviewer);

    expect(Auth::guard('web')->getProvider()->retrieveById($staff->id))->toBeNull()
        ->and(Auth::guard('staff')->getProvider()->retrieveById($student->id))->toBeNull()
        ->and(Auth::guard('web')->getProvider()->retrieveById($student->id)?->id)->toBe($student->id)
        ->and(Auth::guard('staff')->getProvider()->retrieveById($staff->id)?->id)->toBe($staff->id);
});

it('does not honour a forged staff session id for a student, or the reverse', function () {
    $student = applicant();
    $staff = staffWith(Role::Reviewer);

    $this->withSession([Auth::guard('staff')->getName() => $student->id])
        ->get(staffUrl('/admin'))
        ->assertRedirect(route('staff.login'));

    $this->flushSession();

    $this->withSession([Auth::guard('web')->getName() => $staff->id])
        ->get(studentUrl('/dashboard'))
        ->assertRedirect(route('apply.terms'));
});

it('keeps each portal in its own session', function () {
    $this->get(studentUrl('/'));
    expect(config('session.cookie'))->toBe('examis_students_session')
        ->and(config('session.table'))->toBe('sessions');

    $this->refreshApplication();

    $this->get(staffUrl('/staff/login'));
    expect(config('session.cookie'))->toBe('examis_staff_session')
        ->and(config('session.table'))->toBe('staff_sessions')
        ->and(config('session.lifetime'))->toBe(30)
        ->and(config('session.encrypt'))->toBeTrue()
        ->and(config('session.expire_on_close'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('strict');
});

it('starts the session the staff portal way on Fortify routes too', function (string $method, string $path) {
    $this->call($method, staffUrl($path), ['email' => 'nobody@example.com', 'password' => 'x']);

    $store = app('session.store');

    expect($store->getName())->toBe('examis_staff_session')
        ->and($store)->toBeInstanceOf(EncryptedStore::class);
})->with([
    ['GET', '/login'],
    ['POST', '/login'],
    ['GET', '/two-factor-challenge'],
    ['GET', '/user/confirm-password'],
]);

it('scopes its cookies to the host alone', function (string $url) {
    foreach ($this->get($url)->headers->getCookies() as $cookie) {
        expect($cookie->getDomain())->toBeNull()
            ->and($cookie->getPath())->toBe('/');
    }
})->with([fn () => studentUrl('/'), fn () => staffUrl('/staff/login')]);

it('uses host-locked cookie names over HTTPS', function () {
    $this->get('https://'.Portal::Staff->host().'/staff/login');

    expect(config('session.cookie'))->toBe('__Host-examis_staff_session')
        ->and(config('session.secure'))->toBeTrue();
});

it('hides the staff portal from search engines and turns off the service worker', function () {
    $staffPage = $this->get(staffUrl('/staff/login'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($staffPage->getContent())->toContain('data-portal="staff"');

    $studentPage = $this->get(studentUrl('/'))->assertHeaderMissing('X-Robots-Tag');

    expect($studentPage->getContent())->toContain('data-portal="students"');
});

it('encrypts page history only where the browser can', function (string $url, bool $encrypted) {
    $this->get($url);

    expect(config('inertia.history.encrypt'))->toBe($encrypted);
})->with([
    'plain HTTP has no Web Crypto' => ['http://students.examis.test/', false],
    'HTTPS' => ['https://students.examis.test/', true],
]);

it('sends the roots of the staff portal to the right place', function () {
    $this->get(staffUrl('/'))->assertRedirect(route('staff.login'));

    $this->actingAs(staffWith(Role::Reviewer))
        ->get(staffUrl('/'))
        ->assertRedirect(route('admin.home'));
});

it('sends a signed-in staff member away from the sign-in page', function () {
    $this->actingAs(staffWith(Role::Reviewer))
        ->get(route('staff.login'))
        ->assertRedirect(route('admin.home'));
});

it('sends a deactivated staff member back to the staff sign-in', function () {
    $staff = staffWith(Role::Reviewer);
    $staff->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($staff)->get(route('admin.home'))->assertRedirect(route('staff.login'));
    $this->assertGuest();
});

it('serves documents and forms to staff on their own portal only', function () {
    $staff = staffWith(Role::Supervisor);
    $document = ApplicationDocument::factory()->verified()->create();
    $application = Application::factory()->submitted()->create();

    $this->actingAs($staff)->get(route('admin.applications.receipt', $application))->assertOk();
    $this->actingAs($staff)->get(route('documents.show', $document))->assertRedirect(route('apply.terms'));
    $this->actingAs($staff)->get(route('applications.receipt', $application))->assertRedirect(route('apply.terms'));
});

it('links the student form QR code to the student portal from either portal', function () {
    expect(route('receipts.verify', '01966a1e-0000-7000-8000-000000000000'))->toStartWith(studentUrl(''))
        ->and(route('student.qr', '01966a1e-0000-7000-8000-000000000000'))->toStartWith(studentUrl(''));
});

it('offers no way across from the staff sign-in page', function () {
    $this->get(route('staff.login'))->assertOk()->assertDontSee(Portal::Students->host());

    $this->get(studentUrl('/'))->assertDontSee(Portal::Staff->host());
});

it('still refuses a student who posts to the staff sign-in directly', function () {
    $student = User::factory()->create(['email' => 'family@example.com']);

    $this->post(staffUrl('/login'), ['email' => $student->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(Auth::guard('staff')->check())->toBeFalse();
});
