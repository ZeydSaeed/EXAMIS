<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ContentPage;
use App\Models\ExamPeriod;
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

it('switches the interface to English and back', function () {
    $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('locale', 'en')->where('direction', 'ltr'));

    $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
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
    it('shows the status for a matching reference and birth date', function () {
        $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->create(['birth_date' => '2006-03-15']);

        $this->from(route('track.create'))
            ->post(route('track.store'), ['reference_number' => strtolower((string) $application->reference_number), 'birth_date' => '2006-03-15'])
            ->assertRedirect(route('track.create'))
            ->assertSessionHas('track_result.status', 'under_review');
    });

    it('reveals nothing when the birth date does not match', function () {
        $application = Application::factory()->submitted()->create(['birth_date' => '2006-03-15']);

        $this->post(route('track.store'), ['reference_number' => $application->reference_number, 'birth_date' => '2006-03-16'])
            ->assertSessionHasErrors(['reference_number' => __('applications.track.not_found')])
            ->assertSessionMissing('track_result');
    });

    it('is rate limited', function () {
        foreach (range(1, 10) as $attempt) {
            $this->post(route('track.store'), ['reference_number' => 'EX2027-0000019', 'birth_date' => '2000-01-01']);
        }

        $this->post(route('track.store'), ['reference_number' => 'EX2027-0000019', 'birth_date' => '2000-01-01'])->assertTooManyRequests();
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
    $this->actingAs(applicant())->get(route('dashboard'))->assertHeader('Cache-Control', 'no-store, private');
});

it('reports readiness without revealing dependency details', function () {
    $this->getJson(route('health.ready'))
        ->assertOk()
        ->assertExactJson(['status' => 'ready']);
});

it('never shares the full user model with the browser', function () {
    $user = applicant();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.username', $user->username)
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->missing('auth.user.two_factor_secret')
            ->has('translations.common'));
});

it('encrypts page history and rotates the key at logout', function () {
    $user = applicant();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('applicant/dashboard'));
    expect($this->get(route('dashboard'))->viewData('page')['encryptHistory'] ?? false)->toBeTrue();

    $this->post(route('logout'))->assertRedirect('/');

    expect($this->get('/')->viewData('page')['clearHistory'] ?? false)->toBeTrue();
});
