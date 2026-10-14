<?php

namespace App\Services\Documents;

final readonly class ScanResult
{
    private function __construct(public bool $clean, public ?string $reason) {}

    public static function clean(): self
    {
        return new self(true, null);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, $reason);
    }
}
