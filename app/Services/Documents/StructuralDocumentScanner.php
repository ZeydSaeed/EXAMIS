<?php

namespace App\Services\Documents;

/**
 * Verifies that the bytes match the declared type and rejects files with
 * active content. This is defence in depth, not antivirus.
 */
class StructuralDocumentScanner implements DocumentScanner
{
    /**
     * PDF name objects that can execute code, launch programs or smuggle files.
     */
    private const FORBIDDEN_PDF_TOKENS = [
        '/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/RichMedia', '/XFA', '/SubmitForm', '/ImportData',
    ];

    public function __construct(private int $maxImagePixels) {}

    public function scan(string $contents, string $declaredMimeType): ScanResult
    {
        return match ($declaredMimeType) {
            'application/pdf' => $this->scanPdf($contents),
            'image/jpeg', 'image/png' => $this->scanImage($contents, $declaredMimeType),
            default => ScanResult::rejected('unsupported_type'),
        };
    }

    private function scanPdf(string $contents): ScanResult
    {
        if (! str_starts_with($contents, '%PDF-')) {
            return ScanResult::rejected('signature_mismatch');
        }

        foreach (self::FORBIDDEN_PDF_TOKENS as $token) {
            if (preg_match('#'.preg_quote($token, '#').'(?![A-Za-z])#', $contents) === 1) {
                return ScanResult::rejected('active_content');
            }
        }

        return ScanResult::clean();
    }

    private function scanImage(string $contents, string $declaredMimeType): ScanResult
    {
        $signature = $declaredMimeType === 'image/png' ? "\x89PNG\r\n\x1A\n" : "\xFF\xD8\xFF";

        if (! str_starts_with($contents, $signature)) {
            return ScanResult::rejected('signature_mismatch');
        }

        $info = @getimagesizefromstring($contents);

        if ($info === false || $info['mime'] !== $declaredMimeType) {
            return ScanResult::rejected('corrupt_image');
        }

        if ($this->maxImagePixels < $info[0] * $info[1]) {
            return ScanResult::rejected('image_too_large');
        }

        return ScanResult::clean();
    }
}
