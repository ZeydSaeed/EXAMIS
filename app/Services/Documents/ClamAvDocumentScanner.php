<?php

namespace App\Services\Documents;

/**
 * Structural checks first (cheap, and they catch type spoofing an antivirus
 * does not), then the ClamAV engine. An unreachable clamd throws, so the
 * document stays pending instead of becoming downloadable unscanned.
 */
class ClamAvDocumentScanner implements DocumentScanner
{
    public function __construct(private StructuralDocumentScanner $structural, private Clamd $clamd) {}

    public function scan(string $contents, string $declaredMimeType): ScanResult
    {
        $result = $this->structural->scan($contents, $declaredMimeType);

        if (! $result->clean) {
            return $result;
        }

        return $this->clamd->scan($contents) === null ? ScanResult::clean() : ScanResult::rejected('malware');
    }
}
