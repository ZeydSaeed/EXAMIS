<?php

use App\Jobs\ScanApplicationDocument;
use Illuminate\Support\Facades\Log;

it('logs a document scan that failed every retry', function () {
    Log::spy();

    (new ScanApplicationDocument(42))->failed(new RuntimeException('disk unavailable'));

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message, array $context): bool => $context['document_id'] === 42 && $context['error'] === 'disk unavailable',
    );
});
