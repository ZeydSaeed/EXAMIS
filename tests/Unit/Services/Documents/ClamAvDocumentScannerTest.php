<?php

use App\Services\Documents\ClamAvDocumentScanner;
use App\Services\Documents\Clamd;
use App\Services\Documents\StructuralDocumentScanner;

function clamAvScanner(Clamd $clamd): ClamAvDocumentScanner
{
    return new ClamAvDocumentScanner(new StructuralDocumentScanner(maxImagePixels: 10_000), $clamd);
}

it('frames the contents as zINSTREAM chunks ending with a zero-length chunk', function () {
    $contents = str_repeat('a', 8192).'bc';

    expect(Clamd::frame($contents))->toBe(
        "zINSTREAM\0".pack('N', 8192).str_repeat('a', 8192).pack('N', 2).'bc'.pack('N', 0)
    )->and(Clamd::frame(''))->toBe("zINSTREAM\0".pack('N', 0));
});

it('reads clean and infected answers', function () {
    expect(Clamd::parse("stream: OK\0"))->toBeNull()
        ->and(Clamd::parse("stream: Win.Test.EICAR_HDB-1 FOUND\0"))->toBe('Win.Test.EICAR_HDB-1');
});

it('throws on a clamd error so the scan is retried', function (string $answer) {
    Clamd::parse($answer);
})->with([
    'size limit' => ["INSTREAM size limit exceeded. ERROR\0"],
    'empty' => [''],
])->throws(RuntimeException::class);

it('throws when clamd is unreachable', function () {
    (new Clamd('tcp://127.0.0.1:1', 1))->scan('anything');
})->throws(RuntimeException::class);

it('rejects structurally bad files without asking clamd', function () {
    $clamd = Mockery::mock(Clamd::class);
    $clamd->shouldNotReceive('scan');

    expect(clamAvScanner($clamd)->scan('<html></html>', 'application/pdf')->reason)->toBe('signature_mismatch');
});

it('passes a clean file and rejects one clamd flags as malware', function (?string $signature, bool $clean) {
    $pdf = "%PDF-1.7\n1 0 obj << /Type /Catalog >> endobj\n%%EOF";
    $clamd = Mockery::mock(Clamd::class);
    $clamd->shouldReceive('scan')->once()->with($pdf)->andReturn($signature);

    $result = clamAvScanner($clamd)->scan($pdf, 'application/pdf');

    expect($result->clean)->toBe($clean)
        ->and($result->reason)->toBe($clean ? null : 'malware');
})->with([
    'clean' => [null, true],
    'infected' => ['Win.Test.EICAR_HDB-1', false],
]);
