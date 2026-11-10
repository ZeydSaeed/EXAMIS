<?php

namespace App\Services\Documents;

/**
 * Inspects stored file contents before a document becomes downloadable.
 * Implementations: StructuralDocumentScanner (signature and active-content
 * checks) and ClamAvDocumentScanner (the same checks, then the ClamAV
 * daemon), which production uses.
 */
interface DocumentScanner
{
    public function scan(string $contents, string $declaredMimeType): ScanResult;
}
