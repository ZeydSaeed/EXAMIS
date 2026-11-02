<?php

use App\Enums\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Two administrators act on each other at the same moment, from two PostgreSQL
 * connections. This test holds both administrator rows until both workers are
 * queued on them, so the two always collide instead of only sometimes.
 */
beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Needs real concurrent PostgreSQL connections.');
    }

    Artisan::call('migrate:fresh');
});

/**
 * @return array{0: resource, 1: resource}
 */
function startWorker(string $action, User $actor, User $target): array
{
    $process = proc_open(
        [PHP_BINARY, base_path('tests/Concurrency/worker.php'), $action, (string) $actor->id, (string) $target->id],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
        [...getenv(), 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'examis_testing', 'SESSION_DRIVER' => 'database'],
    );

    return [$process, $pipes[1]];
}

function waitForBlockedWorkers(int $count): void
{
    $deadline = microtime(true) + 30;

    while (microtime(true) < $deadline) {
        $blocked = DB::connection('observer')->table('pg_stat_activity')
            ->where('datname', 'examis_testing')
            ->where('wait_event_type', 'Lock')
            ->count();

        if ($blocked >= $count) {
            return;
        }

        usleep(50_000);
    }

    throw new RuntimeException('The workers never reached the lock.');
}

it('keeps an administrator when two act on each other at once', function (string $first, string $second) {
    config(['database.connections.observer' => config('database.connections.pgsql')]);

    $one = staffWith(Role::Administrator);
    $two = staffWith(Role::Administrator);

    DB::beginTransaction();
    DB::table('users')->whereIn('id', [$one->id, $two->id])->lockForUpdate()->get();

    $workers = [startWorker($first, $one, $two), startWorker($second, $two, $one)];

    waitForBlockedWorkers(2);
    DB::rollBack();

    $results = [];

    foreach ($workers as [$process, $output]) {
        $results[] = trim((string) stream_get_contents($output));
        proc_close($process);
    }

    sort($results);

    $remaining = User::query()
        ->whereIn('id', RoleAssignment::query()->where('role', Role::Administrator)->select('user_id'))
        ->whereNull('deactivated_at')
        ->count();

    expect($results)->toBe(['ok', 'rejected'])
        ->and($remaining)->toBe(1);
})->with([
    'both demote' => ['demote', 'demote'],
    'both deactivate' => ['deactivate', 'deactivate'],
    'one of each' => ['demote', 'deactivate'],
]);
