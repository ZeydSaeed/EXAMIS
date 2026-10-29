<?php

use App\Http\Controllers\Public\ApplicationTermsController;
use App\Models\AuditEvent;
use App\Models\ContentPage;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Applying starts with the application terms (the "eligibility" content
 * page); registration is reachable only after accepting them.
 */
beforeEach(function () {
    ContentPage::factory()->create([
        'slug' => 'eligibility',
        'title_ar' => 'شروط التقديم',
        'body_ar' => "١. الشرط الأول.\n\n٢. الشرط الثاني.\n\n٣. الشرط الثالث.",
        'is_published' => true,
    ]);
});

test('the terms page lists each published term without its numbering', function () {
    $this->get(route('apply.terms'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('apply/terms')
            ->where('title', 'شروط التقديم')
            ->where('terms', ['الشرط الأول.', 'الشرط الثاني.', 'الشرط الثالث.']));
});

test('the registration page sends students to the terms first', function () {
    $this->get(route('register'))->assertRedirect(route('apply.terms'));
});

test('the terms must be ticked before moving on', function () {
    $this->post(route('apply.terms.accept'))->assertSessionHasErrors('accept');

    $this->get(route('register'))->assertRedirect(route('apply.terms'));
});

test('accepting the terms leads to registration', function () {
    $this->post(route('apply.terms.accept'), ['accept' => '1'])
        ->assertRedirect(route('register'));

    $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/register'));
});

test('an account cannot be created without accepting the terms', function () {
    $this->post(route('register.store'), [
        'username' => 'skipped.terms',
        'phone' => '07701234567',
        'terms' => '1',
    ])->assertSessionHasErrors(['terms' => __('auth.application_terms_required')]);

    $this->assertGuest();
    expect(User::query()->where('username', 'skipped.terms')->exists())->toBeFalse();
});

test('the acceptance time is recorded with the registration and then cleared', function () {
    $this->post(route('apply.terms.accept'), ['accept' => '1']);

    $this->post(route('register.store'), [
        'username' => 'accepted.terms',
        'phone' => '07701234567',
        'terms' => '1',
    ])->assertSessionHasNoErrors();

    $event = AuditEvent::query()->where('action', 'student.registered')->sole();

    expect($event->metadata['application_terms_accepted_at'] ?? null)->not->toBeNull()
        ->and(session()->has(ApplicationTermsController::SESSION_KEY))->toBeFalse();
});
