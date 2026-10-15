<?php

namespace App\Services\Receipts;

use App\Models\Application;
use App\Models\ApplicationDocument;
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
    private const NAME_PREFIXES = ['عبد', 'أبو', 'ابو'];

    /**
     * Logo printed at the top of the form, when the authority has provided it.
     */
    public const LOGO_PATH = 'images/vocational-education-logo.png';

    public function render(Application $application): string
    {
        $application->loadMissing(['examPeriod', 'currentDocuments.documentType']);
        $locale = app()->getLocale();
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $fonts = $this->fontConfig();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            ...$fonts,
            'margin_top' => 10,
            'margin_bottom' => 8,
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

        $mpdf->WriteHTML(view('pdf.receipt', [
            'application' => $application,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'font' => $fonts['default_font'],
            'logo' => is_file($logo) ? $logo : null,
            'hasPhoto' => $photo !== null,
            'nameParts' => $this->nameParts((string) $application->full_name_ar, 4),
            'motherNameParts' => $this->nameParts((string) $application->mother_name_ar, 3),
            'issuedDocuments' => array_pad(array_slice($application->issued_documents ?? [], 0, 4), 4, null),
            'verificationUrl' => route('receipts.verify', $application->verification_token),
            'generatedAt' => now(),
        ])->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
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

    /**
     * Splits a name into the form's boxes (four for the full name, three for
     * the mother's name), keeping compound names such as "عبد الرحمن" in one
     * box. Returns null when the name does not have that many parts, so the
     * template prints it across all boxes instead.
     *
     * @return list<string>|null
     */
    private function nameParts(string $name, int $count): ?array
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];

        for ($index = 0; $index < count($words); $index++) {
            $word = $words[$index];

            if (in_array($word, self::NAME_PREFIXES, true) && isset($words[$index + 1])) {
                $word .= ' '.$words[++$index];
            }

            $parts[] = $word;
        }

        return count($parts) === $count ? $parts : null;
    }
}
