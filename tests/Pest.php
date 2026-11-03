<?php

use App\Enums\Role;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use App\Models\ExamPeriod;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Real commits between real connections, so no wrapping transaction here.
pest()->extend(TestCase::class)->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Shared builders for EXAMIS feature tests. All data is synthetic.
|
*/

/**
 * Seeds the lookup lists that application validation depends on.
 */
function seedReferenceData(): void
{
    test()->seed(ReferenceDataSeeder::class);
}

/**
 * An open period that requires one PDF-capable document type.
 *
 * @return array{0: ExamPeriod, 1: DocumentType}
 */
function openPeriodRequiringDocument(): array
{
    $type = DocumentType::factory()->create(['code' => 'national_id']);
    $period = ExamPeriod::factory()->requiring($type)->create();

    return [$period, $type];
}

function applicant(): User
{
    return User::factory()->create();
}

function staffWith(Role ...$roles): User
{
    return User::factory()->staff(...$roles)->create();
}

/**
 * A complete draft whose required document is already verified, so it is
 * ready to submit.
 */
function readyDraft(?User $owner = null): Application
{
    [$period, $type] = openPeriodRequiringDocument();

    $application = Application::factory()->complete()->create([
        'user_id' => ($owner ?? applicant())->id,
        'exam_period_id' => $period->id,
    ]);

    ApplicationDocument::factory()->verified()->create([
        'application_id' => $application->id,
        'document_type_id' => $type->id,
    ]);

    return $application;
}

function pdfBytes(string $body = 'BT /F1 12 Tf (Hello) Tj ET'): string
{
    return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\nstream\n{$body}\nendstream\ntrailer << /Root 1 0 R >>\n%%EOF\n";
}

function pngBytes(int $width = 40, int $height = 40): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function uploadedPdf(string $name = 'id-card.pdf', ?string $body = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, pdfBytes($body ?? 'BT (ok) Tj ET'));
}

/**
 * Text as a reader would extract it: Arabic presentation forms normalised
 * back to base letters and whitespace collapsed. Times New Roman maps some
 * joined forms to their Persian code points (ی, ھ, ک), folded back here.
 */
function extractPdfText(string $pdf): string
{
    $text = (new Parser)->parseContent($pdf)->getText();
    $text = Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text;
    $text = strtr($text, ['ی' => 'ي', 'ھ' => 'ه', 'ک' => 'ك']);

    return (string) preg_replace('/\s+/u', ' ', $text);
}

/**
 * PDF stores right-to-left runs in visual order; viewers reorder them for
 * display and selection. Reverse an expected phrase to find it in raw text.
 */
function visualOrder(string $logical): string
{
    // The lam-alef ligature is one glyph, so it comes out of the PDF in logical order.
    preg_match_all('/ل[اأإآ]|./us', $logical, $glyphs);

    return implode('', array_reverse($glyphs[0]));
}
