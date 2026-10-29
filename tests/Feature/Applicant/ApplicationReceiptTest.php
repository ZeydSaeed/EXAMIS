<?php

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditEvent;
use App\Models\DocumentType;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

beforeEach(function () {
    seedReferenceData();
});

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
    return implode('', array_reverse(mb_str_split($logical)));
}

it('renders an A4 receipt whose Arabic text can be extracted', function () {
    $application = Application::factory()->submitted()->create([
        'student_name' => 'عبد الرحمن',
        'father_name' => 'محمد',
        'grandfather_name' => 'عبد الكريم',
        'great_grandfather_name' => 'جاسم',
        'mother_first_name' => 'زهراء',
        'mother_father_name' => 'كاظم',
        'school_governorate_code' => 'BGD',
        'last_grade' => 'الرابع',
        'reference_number' => 'EX2027-0000019',
    ]);

    $response = $this->actingAs($application->user)->get(route('applications.receipt', $application));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $pdf = (string) $response->getContent();
    expect($pdf)->toStartWith('%PDF-')
        ->and($pdf)->toContain('/MediaBox [0 0 595.280 841.890]');

    $text = extractPdfText($pdf);
    expect($text)->not->toContain('EX2027-0000019')
        ->and($text)->toContain(visualOrder('استمارة واقع حال الطالب المتقدم'))
        ->and($text)->toContain(visualOrder('عبد الرحمن'))
        ->and($text)->toContain(visualOrder('عبد الكريم'))
        ->and($text)->toContain(visualOrder('زهراء'))
        ->and($text)->toContain(visualOrder('كاظم'))
        // The last school's governorate ("كربلاء" would not do: its lam-alef is one glyph).
        ->and($text)->toContain(visualOrder('بغداد'))
        ->and($text)->toContain(visualOrder('حي الحسين'))
        ->and($text)->toContain(visualOrder('الرابع'))
        ->and($text)->toContain('2027-2026')
        ->and(preg_match('/[\x{E000}-\x{F8FF}]/u', $text))->toBe(0);
});

it('names the form after the student and their student number', function () {
    $application = Application::factory()->submitted()->create([
        'student_name' => 'علي',
        'father_name' => 'حسن',
        'grandfather_name' => 'كاظم',
        'great_grandfather_name' => 'جاسم',
        'reference_number' => 'EX2027-0000020',
    ]);
    $application->user->forceFill(['student_number' => 12345])->save();

    $response = $this->actingAs($application->user)->get(route('applications.receipt', $application));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toContain('filename=receipt-EX2027-0000020.pdf')
        ->toContain("filename*=utf-8''".rawurlencode('علي حسن كاظم جاسم_12345.pdf'))
        ->and((new Parser)->parseContent((string) $response->getContent())->getDetails()['Title'])
        ->toBe('علي حسن كاظم جاسم_12345');
});

it('follows the form with the request letter, the information form, the required documents and the student card', function () {
    $application = Application::factory()->submitted()->create(['branch_code' => 'IND', 'category_code' => 'IND_ELC']);

    $pdf = (string) $this->actingAs($application->user)->get(route('applications.receipt', $application))->getContent();
    $user = $application->user->refresh();

    $text = extractPdfText($pdf);
    expect((new Parser)->parseContent($pdf)->getPages())->toHaveCount(5)
        ->and($text)->toContain(visualOrder('السيد مدير عام تربية'))
        ->and($text)->toContain(visualOrder('استمارة معلومات الطالب الخاصة بالتقديم'))
        ->and($text)->toContain(visualOrder('المستمسكات المطلوبة'))
        ->and($text)->not->toContain(visualOrder('استمارة اختبار'))
        ->and($text)->toContain(visualOrder('بطاقة الطالب'))
        ->and($user->student_number)->not->toBeNull()
        ->and($text)->toContain((string) $user->student_number);
});

it('adds the test form for computer assembly and maintenance only', function () {
    $application = Application::factory()->submitted()->create(['branch_code' => 'CIT', 'category_code' => 'CIT_HW']);

    $pdf = (string) $this->actingAs($application->user)->get(route('applications.receipt', $application))->getContent();

    $text = extractPdfText($pdf);
    expect((new Parser)->parseContent($pdf)->getPages())->toHaveCount(6)
        ->and($text)->toContain(visualOrder('استمارة اختبار'))
        ->and($text)->toContain(visualOrder('إعدادية العباس المهنية'));
});

it('prints the verified personal photo in the photo box', function () {
    Storage::fake('documents');
    $application = Application::factory()->submitted()->create();
    $photo = ApplicationDocument::factory()->verified()->create([
        'application_id' => $application->id,
        'document_type_id' => DocumentType::factory()->create(['is_photo' => true])->id,
        'disk' => 'documents',
        'path' => 'photos/student.png',
    ]);
    Storage::disk('documents')->put($photo->path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

    $withPhoto = (string) $this->actingAs($application->user)->get(route('applications.receipt', $application))->getContent();
    $photo->forceFill(['status' => DocumentStatus::Pending])->save();
    $withoutVerifiedPhoto = (string) $this->actingAs($application->user)->get(route('applications.receipt', $application))->getContent();

    // The authority logo (when provided) is an image too, so compare counts.
    expect(substr_count($withPhoto, '/Subtype /Image'))
        ->toBeGreaterThan(substr_count($withoutVerifiedPhoto, '/Subtype /Image'))
        ->and(extractPdfText($withoutVerifiedPhoto))->toContain(visualOrder('الصورة الشخصية'));
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
