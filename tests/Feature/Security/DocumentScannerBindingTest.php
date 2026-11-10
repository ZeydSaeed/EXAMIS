<?php

use App\Services\Documents\ClamAvDocumentScanner;
use App\Services\Documents\DocumentScanner;
use App\Services\Documents\StructuralDocumentScanner;

it('binds the scanner named in the configuration', function (string $name, string $class) {
    config(['examis.documents.scanner' => $name]);

    expect(app(DocumentScanner::class))->toBeInstanceOf($class);
})->with([
    'structural' => ['structural', StructuralDocumentScanner::class],
    'clamav' => ['clamav', ClamAvDocumentScanner::class],
]);
