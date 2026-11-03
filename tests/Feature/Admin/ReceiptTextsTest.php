<?php

use App\Enums\Role;
use App\Models\Application;
use App\Models\ContentPage;
use App\Services\Receipts\ReceiptTexts;
use Database\Seeders\ContentPageSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedReferenceData();
    ContentPageSeeder::seedReceiptTexts();
});

function receiptTextData(string $slug, array $overrides = []): array
{
    return [...ReceiptTexts::defaults($slug), 'is_published' => true, ...$overrides];
}

it('lists the printed form texts apart from the portal pages', function () {
    $this->actingAs(staffWith(Role::Administrator))
        ->get(route('admin.content.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('receiptPages', 4)
            ->where('receiptPages.0.slug', ReceiptTexts::LETTER)
            ->where('pages', fn ($pages) => collect($pages)->pluck('slug')->intersect(ReceiptTexts::SLUGS)->isEmpty()));
});

it('prints the edited texts, numbering the list items', function () {
    $this->actingAs(staffWith(Role::Administrator))
        ->put(route('admin.content.update', ReceiptTexts::STAGES), receiptTextData(ReceiptTexts::STAGES, [
            'body_ar' => "مرحلة أولى للتجربة\n\nمرحلة ثانية للتجربة",
        ]))
        ->assertSessionHasNoErrors();

    $this->put(route('admin.content.update', ReceiptTexts::PLEDGE), receiptTextData(ReceiptTexts::PLEDGE, [
        'body_ar' => 'أتعهد بنص معدل للتجربة',
    ]))->assertSessionHasNoErrors();

    $page = ContentPage::query()->where('slug', ReceiptTexts::STAGES)->sole();
    expect($page->is_published)->toBeFalse();

    $application = Application::factory()->submitted()->create(['branch_code' => 'COM', 'category_code' => 'COM_ACC']);
    $text = extractPdfText((string) $this->actingAs($application->user)->get(route('applications.receipt', $application))->getContent());

    expect($text)->toContain(visualOrder('مرحلة ثانية للتجربة'))
        ->and($text)->toContain(visualOrder('أتعهد بنص معدل للتجربة'))
        ->and($text)->not->toContain(visualOrder('سحب استمارة التقديم'));
});

it('fills the letter placeholders and refuses unknown ones', function () {
    $admin = staffWith(Role::Administrator);

    $this->actingAs($admin)
        ->put(route('admin.content.update', ReceiptTexts::LETTER), receiptTextData(ReceiptTexts::LETTER, ['body_ar' => 'طلب {الاسم}']))
        ->assertSessionHasErrors('body_ar');

    $this->put(route('admin.content.update', ReceiptTexts::LETTER), receiptTextData(ReceiptTexts::LETTER, [
        'body_ar' => 'أرجو قبول اشتراكي في الفرع ({الفرع}) لعام {العام_الدراسي}.',
    ]))->assertSessionHasNoErrors();

    $application = Application::factory()->submitted()->create(['branch_code' => 'COM', 'category_code' => 'COM_ACC']);
    $text = extractPdfText((string) $this->actingAs($application->user)->get(route('applications.receipt', $application))->getContent());

    expect($text)->toContain(visualOrder('أرجو قبول اشتراكي'))
        ->and($text)->not->toContain('{');
});

it('falls back to the built-in wording until a text exists', function () {
    ContentPage::query()->whereIn('slug', ReceiptTexts::SLUGS)->delete();

    expect(ReceiptTexts::current()[ReceiptTexts::PLEDGE]['paragraphs'])->toBe([__('receipt.pledge.text')])
        ->and(ReceiptTexts::current()[ReceiptTexts::STAGES]['paragraphs'])->toHaveCount(4);
});

it('never shows a printed form text as a portal page', function () {
    ContentPage::query()->where('slug', ReceiptTexts::PLEDGE)->update(['is_published' => true]);

    $this->get(route('pages.show', ReceiptTexts::PLEDGE))->assertNotFound();
});
