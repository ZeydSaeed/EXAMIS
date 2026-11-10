<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Kept for old instructions. The k6 suite now uses PerfSeeder, whose
 * accounts sign in the way students and staff really do (ADR-0009,
 * ADR-0010) and which refuses any database SyntheticTarget does not allow.
 */
class LoadTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PerfSeeder::class);
    }
}
