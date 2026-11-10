<?php

use App\Enums\Portal;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

function assertBaselineHeaders(TestResponse $response): void
{
    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($policy)->toContain("default-src 'self'")
        ->and($policy)->toContain("frame-ancestors 'none'")
        ->and($policy)->not->toContain("'unsafe-eval'");
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
}

it('sends the security headers on error pages', function (string $method, string $path, array $query, int $status) {
    foreach ([Portal::Students, Portal::Staff] as $portal) {
        $response = $this->call($method, 'https://'.$portal->host().$path, $query);

        $response->assertStatus($status);
        assertBaselineHeaders($response);
    }
})->with([
    'no such page' => ['GET', '/no-such-page', [], 404],
    'wrong method' => ['DELETE', '/health/ready', [], 405],
    'NUL byte' => ['GET', '/', ['q' => "a\0b"], 400],
]);

it('answers the health checks without a session or cookies', function (string $path) {
    $response = $this->get('https://'.Portal::Students->host().$path);

    $response->assertOk();
    expect($response->headers->getCookies())->toBe([]);
    assertBaselineHeaders($response);
})->with(['/up', '/health/ready']);

it('signs nobody in from a remember cookie on a page outside the session', function () {
    Event::fake([Login::class]);
    $user = applicant();
    $cookie = Auth::guard('web')->getRecallerName();
    $value = $user->getAuthIdentifier().'|'.$user->getRememberToken().'|'.$user->getAuthPassword();

    $this->withUnencryptedCookie($cookie, $value)
        ->get('https://'.Portal::Students->host().'/no-such-page')
        ->assertNotFound();
    $this->withUnencryptedCookie($cookie, $value)
        ->get('https://'.Portal::Students->host().'/health/ready')
        ->assertOk();

    Event::assertNotDispatched(Login::class);
});

it('sends HSTS over HTTPS only, with the configured max-age', function () {
    config(['examis.security.hsts' => true, 'examis.security.hsts_max_age' => 300]);

    $this->get('https://'.Portal::Students->host().'/')
        ->assertHeader('Strict-Transport-Security', 'max-age=300; includeSubDomains');
    $this->get('https://'.Portal::Staff->host().'/no-such-page')
        ->assertHeader('Strict-Transport-Security', 'max-age=300; includeSubDomains');
    $this->get('http://'.Portal::Students->host().'/')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('sends no HSTS while it is off', function () {
    config(['examis.security.hsts' => false]);

    $this->get('https://'.Portal::Students->host().'/')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('keeps the session cookies host-only, secure and HTTP-only on both portals', function (Portal $portal, string $path, string $sameSite) {
    $response = $this->get('https://'.$portal->host().$path);

    $session = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === '__Host-examis_'.$portal->value.'_session');

    expect($session)->not->toBeNull()
        ->and($session->isSecure())->toBeTrue()
        ->and($session->isHttpOnly())->toBeTrue()
        ->and($session->getPath())->toBe('/')
        ->and($session->getDomain())->toBeNull()
        ->and($session->getSameSite())->toBe($sameSite);
})->with([
    'students' => [Portal::Students, '/', 'lax'],
    'staff' => [Portal::Staff, '/staff/login', 'strict'],
]);
