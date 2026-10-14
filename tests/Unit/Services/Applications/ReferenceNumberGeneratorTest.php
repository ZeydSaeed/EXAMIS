<?php

use App\Services\Applications\ReferenceNumberGenerator;

it('computes a weighted mod-11 check digit', function (string $digits, string $check) {
    expect(ReferenceNumberGenerator::checkDigit($digits))->toBe($check);
})->with([
    ['000001', '9'],
    ['000123', '6'],
    ['123456', '0'],
    ['000010', '8'],
    ['000006', 'X'],
]);

it('accepts well-formed reference numbers', function () {
    $digits = '004217';

    expect(ReferenceNumberGenerator::isWellFormed('EX2027-'.$digits.ReferenceNumberGenerator::checkDigit($digits)))->toBeTrue();
});

it('rejects a single mistyped digit', function () {
    $digits = '004217';
    $check = ReferenceNumberGenerator::checkDigit($digits);

    expect(ReferenceNumberGenerator::isWellFormed('EX2027-004218'.$check))->toBeFalse();
});

it('rejects malformed input', function (string $reference) {
    expect(ReferenceNumberGenerator::isWellFormed($reference))->toBeFalse();
})->with([
    'empty' => [''],
    'no period' => ['-0000019'],
    'lowercase period' => ['ex2027-0000019'],
    'sql fragment' => ["EX2027-000001' OR 1=1"],
    'too short' => ['EX2027-123'],
]);
