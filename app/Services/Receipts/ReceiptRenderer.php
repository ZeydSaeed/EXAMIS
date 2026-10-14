<?php

namespace App\Services\Receipts;

use App\Enums\ReferenceType;
use App\Models\Application;
use App\Services\ReferenceData;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Renders the A4 submission receipt with mPDF, which shapes Arabic letters
 * and applies bidi so text stays selectable and extractable.
 */
class ReceiptRenderer
{
    public function __construct(private ReferenceData $referenceData) {}

    public function render(Application $application): string
    {
        $application->loadMissing('examPeriod');
        $locale = app()->getLocale();
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'margin_top' => 14,
            'margin_bottom' => 14,
            'margin_left' => 14,
            'margin_right' => 14,
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

        $mpdf->WriteHTML(view('pdf.receipt', [
            'application' => $application,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'organization' => $locale === 'ar' ? config('examis.organization.name_ar') : config('examis.organization.name_en'),
            'labels' => [
                'governorate' => $this->referenceData->name(ReferenceType::Governorate, $application->governorate_code),
                'branch' => $this->referenceData->name(ReferenceType::Branch, $application->branch_code),
                'category' => $this->referenceData->name(ReferenceType::Category, $application->category_code),
            ],
            'verificationUrl' => route('receipts.verify', $application->verification_token),
            'generatedAt' => now(),
        ])->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
