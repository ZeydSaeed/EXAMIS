<?php

namespace App\Services\Applications;

use App\Models\Application;
use App\Models\ExamPeriod;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Issues reference numbers of the form `<PERIOD>-<8 random digits><check digit>`.
 * The digits are random because the number alone opens a student's form on
 * the tracking page: a sequence would let anyone walk through all of them.
 * Must run inside the submission transaction: the period's row is locked, so
 * concurrent submissions cannot pick the same number. `last_sequence` counts
 * the numbers issued.
 */
class ReferenceNumberGenerator
{
    public function next(ExamPeriod $period): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Reference numbers must be issued inside a transaction.');
        }

        $locked = ExamPeriod::query()->whereKey($period->getKey())->lockForUpdate()->firstOrFail();

        ExamPeriod::query()->whereKey($period->getKey())->update(['last_sequence' => $locked->last_sequence + 1]);

        do {
            $digits = (string) random_int(10_000_000, 99_999_999);
            $reference = $locked->code.'-'.$digits.self::checkDigit($digits);
        } while (Application::query()->where('reference_number', $reference)->exists());

        return $reference;
    }

    /**
     * Weighted mod-11 check digit that catches single-digit typos and most
     * transpositions when an applicant reads the number back.
     */
    public static function checkDigit(string $digits): string
    {
        $sum = 0;
        $weight = 2;

        foreach (array_reverse(str_split($digits)) as $digit) {
            $sum += (int) $digit * $weight;
            $weight = $weight === 7 ? 2 : $weight + 1;
        }

        $check = (11 - ($sum % 11)) % 11;

        return $check === 10 ? 'X' : (string) $check;
    }

    public static function isWellFormed(string $reference): bool
    {
        if (preg_match('/^[A-Z0-9]{2,12}-(\d{6,})([0-9X])$/', $reference, $matches) !== 1) {
            return false;
        }

        return self::checkDigit($matches[1]) === $matches[2];
    }
}
