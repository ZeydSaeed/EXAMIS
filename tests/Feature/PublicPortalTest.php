<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ContentPage;
use App\Models\ExamPeriod;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('lists only periods that are open right now on the home page', function () {
    ExamPeriod::factory()->create(['name_ar' => 'دورة مفتوحة']);
    ExamPeriod::factory()->closed()->create(['name_ar' => 'دورة مغلقة']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('openPeriods', 1)
            ->where('openPeriods.0.name', 'دورة مفتوحة')
            ->where('locale', 'ar')
            ->where('direction', 'rtl'));
});

it('shows published content pages and hides unpublished ones', function () {
    ContentPage::factory()->create(['slug' => 'faq', 'body_ar' => '<script>alert(1)</script> نص']);
    ContentPage::factory()->create(['slug' => 'draft-page', 'is_published' => false]);

    $this->get(route('pages.show', 'faq'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/page')->where('page.body', '<script>alert(1)</script> نص'));

    $this->get(route('pages.show', 'draft-page'))->assertNotFound();
});

describe('tracking without signing in', function () {
    it('shows the status for a reference number alone', function () {
        $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->create();

        $this->from(route('track.create'))
            ->post(route('track.store'), ['reference_number' => strtolower((string) $application->reference_number)])
            ->assertRedirect(route('track.create'))
            ->assertSessionHas('track_result.status', 'under_review');
    });

    it('reveals nothing for an unknown number', function () {
        $this->post(route('track.store'), ['reference_number' => 'EX2027-0000019'])
            ->assertSessionHasErrors(['reference_number' => __('applications.track.not_found')])
            ->assertSessionMissing('track_result');
    });

    it('never hands out the form', function () {
        $application = Application::factory()->submitted()->create();

        $this->post(route('track.store'), ['reference_number' => $application->reference_number]);

        expect(session('track_result'))->not->toHaveKey('form_url');
        $this->get('/track/'.$application->uuid.'/form')->assertNotFound();
    });

    it('stops once the account has closed', function () {
        $application = Application::factory()->submitted()->create();
        $application->user->forceFill(['closed_at' => now()])->save();

        $this->post(route('track.store'), ['reference_number' => $application->reference_number])
            ->assertSessionHasErrors(['reference_number' => __('applications.track.closed')])
            ->assertSessionMissing('track_result');
    });

    it('is rate limited', function () {
        foreach (range(1, 10) as $attempt) {
            $this->post(route('track.store'), ['reference_number' => 'EX2027-0000019']);
        }

        $this->post(route('track.store'), ['reference_number' => 'EX2027-0000019'])->assertTooManyRequests();
    });
});

it('verifies a genuine receipt without exposing personal data', function () {
    $application = Application::factory()->submitted()->create(['full_name_ar' => 'اسم سري للطالب']);

    $this->get(route('receipts.verify', $application->verification_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/verify')
            ->where('receipt.reference_number', $application->reference_number)
            ->missing('receipt.full_name_ar'));

    $this->get(route('receipts.verify', (string) Str::uuid()))
        ->assertInertia(fn (Assert $page) => $page->where('receipt', null));
});

it('does not verify drafts', function () {
    $draft = Application::factory()->complete()->create();

    $this->get(route('receipts.verify', $draft->verification_token))
        ->assertInertia(fn (Assert $page) => $page->where('receipt', null));
});

it('sends security headers with a nonce-based script policy', function () {
    $response = $this->get(route('home'));

    $policy = (string) $response->headers->get('Content-Security-Policy');
    expect($policy)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/")
        ->and($policy)->toContain("frame-ancestors 'none'")
        ->and($policy)->toContain("object-src 'none'")
        ->and($policy)->not->toContain("'unsafe-eval'");
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('marks authenticated responses as not cacheable', function () {
    $this->actingAs(applicant())->get(route('apply.specialization'))->assertHeader('Cache-Control', 'no-store, private');
});

it('reports readiness without revealing dependency details', function () {
    $this->getJson(route('health.ready'))
        ->assertOk()
        ->assertExactJson(['status' => 'ready']);
});

it('answers liveness with a status only, without a page or third-party script', function () {
    $response = $this->get('/up');

    $response->assertOk()
        ->assertExactJson(['status' => 'up'])
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->getContent())->not->toContain('<script');
});

it('never shares the full user model with the browser', function () {
    $user = applicant();

    $this->actingAs($user)->get(route('apply.specialization'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.username', $user->username)
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->missing('auth.user.two_factor_secret')
            ->has('translations.common'));
});

it('encrypts page history and rotates the key at logout', function () {
    URL::forceScheme('https');
    $user = applicant();

    $this->actingAs($user)->get(route('apply.specialization'))
        ->assertInertia(fn (Assert $page) => $page->component('apply/specialization'));
    expect($this->get(route('apply.specialization'))->viewData('page')['encryptHistory'] ?? false)->toBeTrue();

    $this->post(route('student.logout'))->assertRedirect(route('start'));

    expect($this->get('/')->viewData('page')['clearHistory'] ?? false)->toBeTrue();
});

it('opens on the standalone start page with both logos, and keeps the portal home at /portal', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('start'));

    expect(route('home', absolute: false))->toBe('/portal')
        ->and(public_path('images/moe-iraq-logo.webp'))->toBeFile()
        ->and(public_path('images/vocational-education-logo.webp'))->toBeFile();
});
