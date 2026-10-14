<?php

use App\Enums\Role;
use App\Models\Application;
use App\Models\AuditEvent;
use Smalot\PdfParser\Parser;

beforeEach(function () {
    seedReferenceData();
});

/**
 * Text as a reader would extract it: Arabic presentation forms normalised
 * back to base letters and whitespace collapsed.
 */
function extractPdfText(string $pdf): string
{
    $text = (new Parser)->parseContent($pdf)->getText();
    $text = Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text;

    return (string) preg_replace('/\s+/u', ' ', $text);
}

/**
 * PDF stores right-to-left runs in visual order; viewers reorder them for
 * display and selection. Reverse an expected phrase to find it in raw text.
 */
function visualOrder(string $logical): string
{
    return implode('', array_reverse(mb_str_split($logical)));
}

it('renders an A4 receipt whose Arabic text can be extracted', function () {
    $application = Application::factory()->submitted()->create([
        'full_name_ar' => 'عبد الرحمن محمد عبد الكريم الساعدي',
        'reference_number' => 'EX2027-0000019',
    ]);

    $response = $this->actingAs($application->user)->get(route('applications.receipt', $application));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $pdf = (string) $response->getContent();
    expect($pdf)->toStartWith('%PDF-')
        ->and($pdf)->toContain('/MediaBox [0 0 595.280 841.890]');

    $text = extractPdfText($pdf);
    expect($text)->toContain('EX2027-0000019')
        ->and($text)->toContain(visualOrder('عبد الرحمن محمد عبد الكريم الساعدي'))
        ->and($text)->toContain(visualOrder('بغداد'))
        ->and($text)->toContain(visualOrder('العلمي'))
        ->and($text)->toContain('(2026-2027)')
        ->and(preg_match('/[\x{E000}-\x{F8FF}]/u', $text))->toBe(0);
});

it('encodes only the verification link in the QR code, not personal data', function () {
    $application = Application::factory()->submitted()->create(['national_id_number' => '199912345678']);

    $pdf = (string) $this->actingAs($application->user)->get(route('applications.receipt', $application))->getContent();

    expect(extractPdfText($pdf))->not->toContain('199912345678');
});

it('audits every receipt issued', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs($application->user)->get(route('applications.receipt', $application))->assertOk();

    expect(AuditEvent::query()->where('action', 'receipt.downloaded')->where('subject_id', $application->id)->count())->toBe(1);
});

it('has no receipt before submission', function () {
    $application = Application::factory()->complete()->create();

    $this->actingAs($application->user)->get(route('applications.receipt', $application))->assertForbidden();
});

it('answers 404 to other applicants', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs(applicant())->get(route('applications.receipt', $application))->assertNotFound();
});

it('lets staff who can see the application reprint the receipt', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs(staffWith(Role::Reviewer))->get(route('applications.receipt', $application))->assertOk();
});
