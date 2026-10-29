<?php

namespace App\Services\Receipts;

use App\Enums\ReferenceType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Services\ReferenceData;
use App\Services\Students\StudentIdentity;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Throwable;

/**
 * Renders the A4 receipt as the official student status form (استمارة واقع
 * حال) filled with the submitted data, plus the reference number and the
 * verification QR code (ADR-0007). mPDF shapes Arabic letters and applies
 * bidi so text stays selectable and extractable.
 */
class ReceiptRenderer
{
    /**
     * Words that start a compound Arabic name ("عبد الرحمن", "أبو بكر").
     */

    /**
     * Logo printed at the top of the form, when the authority has provided it.
     */
    public const LOGO_PATH = 'images/vocational-education-logo.png';

    /**
     * Muted shade of each branch's colour for the form's label cells, with a
     * lighter tint for sub-headings. Any other branch, tourism and hospitality
     * included, is printed white.
     *
     * @var array<string, array{string, string}>
     */
    public const BRANCH_SHADES = [
        'IND' => ['#e39191', '#f4d3d3'],
        'COM' => ['#f2cf66', '#faecc2'],
        'AGR' => ['#99cc8f', '#d9edd4'],
        'APA' => ['#8fb3e0', '#d5e3f4'],
        'CIT' => ['#bfbfbf', '#e6e6e6'],
    ];

    /**
     * Only applicants to this branch and specialization sit a practical test,
     * so only their receipt carries the test form.
     *
     * @var array{string, string}
     */
    public const TEST_FORM_FOR = ['CIT', 'CIT_HW'];

    public function __construct(private StudentIdentity $identity) {}

    public function render(Application $application): string
    {
        $application->loadMissing(['user', 'examPeriod', 'currentDocuments.documentType']);
        // The student card on the last page needs the student number and QR id.
        $this->identity->ensureIdentity($application->user);
        $locale = app()->getLocale();
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $fonts = $this->fontConfig();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            ...$fonts,
            'margin_top' => 12,
            'margin_bottom' => 11,
            'margin_left' => 12,
            'margin_right' => 12,
            'autoScriptToLang' => true,
            // Keep DejaVu Sans for Arabic: the language fallback font (XB Riyaz)
            // maps contextual letter forms to private-use code points, which
            // silently drops letters from copied or extracted text.
            'autoLangToFont' => false,
            'autoArabic' => true,
        ]);

        $mpdf->SetDirectionality($locale === 'ar' ? 'rtl' : 'ltr');
        $mpdf->SetTitle(__('receipt.title').' '.$application->reference_number);
        $mpdf->SetAuthor((string) config('examis.organization.name_en'));
        $mpdf->SetCreator('EXAMIS');

        $photo = $this->photo($application);

        if ($photo !== null) {
            $mpdf->imageVars['photo'] = $photo;
        }

        $logo = public_path(self::LOGO_PATH);
        $referenceData = app(ReferenceData::class);

        $mpdf->WriteHTML(view('pdf.receipt', [
            'application' => $application,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'font' => $fonts['default_font'],
            'logo' => is_file($logo) ? $logo : null,
            'hasPhoto' => $photo !== null,
            'shades' => self::BRANCH_SHADES[$application->branch_code] ?? ['#ffffff', '#ffffff'],
            'branchShades' => self::BRANCH_SHADES,
            'hasTestForm' => [$application->branch_code, $application->category_code] === self::TEST_FORM_FOR,
            // Shown left to right so that the first year reads first, on the right.
            'academicYear' => implode('-', array_reverse(explode('-', $application->examPeriod->academic_year))),
            'student' => $application->user,
            'studentQrUrl' => route('student.qr', $application->user->public_id),
            'fullName' => $this->joined($application->only(Application::NAME_PARTS), ' ') ?? $application->full_name_ar,
            'motherName' => $this->joined($application->only(Application::MOTHER_NAME_PARTS), ' ') ?? $application->mother_name_ar,
            'branch' => $referenceData->name(ReferenceType::Branch, $application->branch_code),
            'specialization' => $referenceData->name(ReferenceType::Category, $application->category_code),
            'schoolGovernorate' => $referenceData->name(ReferenceType::Governorate, $application->school_governorate_code),
            'verificationUrl' => route('receipts.verify', $application->verification_token),
            'generatedAt' => now(),
        ])->render());

        $this->drawFrame($mpdf);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * The filled values joined, or null when none is filled.
     *
     * @param  array<array-key, mixed>  $values
     */
    private function joined(array $values, string $separator): ?string
    {
        $values = array_filter($values, fn (mixed $value): bool => filled($value));

        return $values === [] ? null : implode($separator, $values);
    }

    /**
     * A thin double frame inside every page: two 0.3 mm lines 1.2 mm apart,
     * drawn at fixed page coordinates so it never shifts with the content.
     */
    private function drawFrame(Mpdf $mpdf): void
    {
        $current = $mpdf->page;
        $mpdf->SetDrawColor(0, 0, 0);
        $mpdf->SetLineWidth(0.3);

        for ($page = 1; $page <= count($mpdf->pages); $page++) {
            $mpdf->page = $page;

            foreach ([6.0, 7.2] as $inset) {
                $mpdf->Rect($inset, $inset, $mpdf->w - 2 * $inset, $mpdf->h - 2 * $inset);
            }
        }

        $mpdf->page = $current;
    }

    /**
     * Times New Roman when its files are available (config
     * `examis.receipt.font_dir`), shaped through its OpenType tables so
     * Arabic joins correctly; DejaVu Sans otherwise.
     *
     * @return array<string, mixed>
     */
    private function fontConfig(): array
    {
        $directory = rtrim((string) config('examis.receipt.font_dir'), '/\\');

        if ($directory === '' || ! is_file($directory.'/times.ttf') || ! is_file($directory.'/timesbd.ttf')) {
            return ['default_font' => 'dejavusans'];
        }

        $defaults = (new FontVariables)->getDefaults();

        return [
            'fontDir' => [...(new ConfigVariables)->getDefaults()['fontDir'], $directory],
            'fontdata' => $defaults['fontdata'] + [
                'timesnewroman' => [
                    'R' => 'times.ttf',
                    'B' => 'timesbd.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => 'timesnewroman',
        ];
    }

    /**
     * The verified personal photo, read from the private documents disk.
     * Only a verified file is used, as for every other document download.
     */
    private function photo(Application $application): ?string
    {
        $document = $application->currentDocuments
            ->first(fn (ApplicationDocument $document): bool => $document->documentType->is_photo && $document->isDownloadable());

        if ($document === null) {
            return null;
        }

        try {
            return Storage::disk($document->disk)->get($document->path);
        } catch (Throwable) {
            return null;
        }
    }
}
