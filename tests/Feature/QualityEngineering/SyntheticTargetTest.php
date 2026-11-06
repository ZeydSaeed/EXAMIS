<?php

use Database\Seeders\PerfSeeder;
use Database\Seeders\Support\SyntheticTarget;
use Illuminate\Support\Facades\DB;

/**
 * The gate in front of synthetic data must refuse every target that is not
 * an approved, marked test database on this machine, whichever rule breaks.
 */
function approvedPerfTarget(array $overrides = []): array
{
    return [
        'environment' => 'perf',
        'production' => false,
        'app_name' => 'EXAMIS',
        'portal_hosts' => ['students.perf.localhost', 'staff.perf.localhost'],
        'connection' => 'pgsql',
        'database' => 'examis_perf',
        'host' => '127.0.0.1',
        'marker' => SyntheticTarget::MARKER,
        ...$overrides,
    ];
}

test('accepts the approved perf database', function () {
    expect(SyntheticTarget::check(approvedPerfTarget()))->toBe([]);
});

test('accepts the in-memory test database', function () {
    expect(SyntheticTarget::check(approvedPerfTarget([
        'environment' => 'testing',
        'portal_hosts' => ['students.examis.test', 'staff.examis.test'],
        'connection' => 'sqlite',
        'database' => ':memory:',
        'host' => null,
        'marker' => null,
    ])))->toBe([]);
});

test('refuses a database that is not on the allow-list', function (string $connection, string $database) {
    expect(SyntheticTarget::check(approvedPerfTarget(['connection' => $connection, 'database' => $database])))
        ->toContain("database [{$connection}:{$database}] is not an approved test database");
})->with([
    'the live database' => ['pgsql', 'sis'],
    'staging' => ['pgsql', 'examis_staging'],
    'development' => ['pgsql', 'EXAMIS'],
    'production name' => ['pgsql', 'examis'],
    'a file database' => ['sqlite', 'database/database.sqlite'],
    'name and driver that contradict' => ['sqlite', 'examis_perf'],
]);

test('refuses a database server on another machine', function (?string $host) {
    expect(SyntheticTarget::check(approvedPerfTarget(['host' => $host])))
        ->toContain('database server ['.($host ?? 'none').'] is not on this machine');
})->with(['10.0.0.5', '178.105.246.187', 'db.mahani-iq.com', null]);

test('refuses an environment that is not a test environment', function (string $environment, bool $production) {
    expect(SyntheticTarget::check(approvedPerfTarget(['environment' => $environment, 'production' => $production])))
        ->toContain("environment [{$environment}] is not a test environment");
})->with([
    'production' => ['production', true],
    'staging' => ['staging', false],
    'unknown' => ['qa', false],
]);

test('refuses portal hosts reachable from the internet', function (string $host) {
    expect(SyntheticTarget::check(approvedPerfTarget(['portal_hosts' => [$host, 'staff.perf.localhost']])))
        ->toContain("portal host [{$host}] is reachable from outside this machine");
})->with(['students.mahani-iq.com', 'students-staging.mahani-iq.com', 'localhost.mahani-iq.com', '']);

test('refuses missing or contradictory settings', function () {
    expect(SyntheticTarget::check(approvedPerfTarget(['portal_hosts' => []])))
        ->toContain('portal hosts are not configured')
        ->and(SyntheticTarget::check(approvedPerfTarget(['app_name' => 'Laravel'])))
        ->toContain('application [Laravel] is not EXAMIS');
});

test('refuses a PostgreSQL database without the synthetic marker', function (?string $marker) {
    expect(SyntheticTarget::check(approvedPerfTarget(['marker' => $marker])))
        ->toContain("database [examis_perf] is not marked '".SyntheticTarget::MARKER."'");
})->with([null, '', 'production', 'external_link:synthetic ']);

test('stops the seeder before it writes anything', function () {
    config(['examis.portals.students' => 'students.mahani-iq.com']);

    expect(fn () => $this->seed(PerfSeeder::class))
        ->toThrow(RuntimeException::class, 'portal host [students.mahani-iq.com] is reachable from outside this machine');

    expect(DB::table('users')->count())->toBe(0)
        ->and(DB::table('exam_periods')->count())->toBe(0);
});

test('this test run itself is an allowed target', function () {
    if (DB::getDriverName() === 'pgsql' && SyntheticTarget::facts()['marker'] !== SyntheticTarget::MARKER) {
        $this->markTestSkipped("examis_testing has no '".SyntheticTarget::MARKER."' marker yet (needs the owner's approval).");
    }

    expect(SyntheticTarget::check(SyntheticTarget::facts()))->toBe([]);
});
