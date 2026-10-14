<?php

use App\Services\Documents\StructuralDocumentScanner;

function scanner(): StructuralDocumentScanner
{
    return new StructuralDocumentScanner(maxImagePixels: 10_000);
}

function tinyPng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

it('accepts a plain PDF', function () {
    $result = scanner()->scan("%PDF-1.7\n1 0 obj << /Type /Catalog >> endobj\n%%EOF", 'application/pdf');

    expect($result->clean)->toBeTrue();
});

it('rejects PDFs carrying active content', function (string $token) {
    $result = scanner()->scan("%PDF-1.7\n1 0 obj << {$token} (app.alert(1)) >> endobj\n%%EOF", 'application/pdf');

    expect($result->clean)->toBeFalse()
        ->and($result->reason)->toBe('active_content');
})->with(['/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/XFA']);

it('does not confuse longer PDF names with forbidden tokens', function () {
    $result = scanner()->scan("%PDF-1.7\n<< /JSONData 1 >>\n%%EOF", 'application/pdf');

    expect($result->clean)->toBeTrue();
});

it('rejects content whose signature does not match the declared type', function (string $contents, string $mime) {
    expect(scanner()->scan($contents, $mime)->reason)->toBe('signature_mismatch');
})->with([
    'html posing as pdf' => ['<html><script>alert(1)</script></html>', 'application/pdf'],
    'pdf posing as png' => ["%PDF-1.7\n%%EOF", 'image/png'],
    'executable posing as jpeg' => ["MZ\x90\x00\x03", 'image/jpeg'],
]);

it('accepts a valid PNG within the pixel budget', function () {
    expect(scanner()->scan(tinyPng(50, 50), 'image/png')->clean)->toBeTrue();
});

it('rejects images over the pixel budget', function () {
    expect(scanner()->scan(tinyPng(200, 200), 'image/png')->reason)->toBe('image_too_large');
});

it('rejects a truncated image with a valid signature', function () {
    expect(scanner()->scan("\x89PNG\r\n\x1A\n\x00\x00garbage", 'image/png')->reason)->toBe('corrupt_image');
});

it('rejects unsupported types', function () {
    expect(scanner()->scan('anything', 'text/html')->reason)->toBe('unsupported_type');
});
