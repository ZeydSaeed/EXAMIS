<?php

namespace App\Services\Documents;

/**
 * Inspects stored file contents before a document becomes downloadable.
 * Implementations: StructuralDocumentScanner (signature and active-content
 * checks). An antivirus-backed implementation (e.g. ClamAV) is required in
 * production and plugs in through this contract.
 */
interface DocumentScanner
{
    public function scan(string $contents, string $declaredMimeType): ScanResult;
}
