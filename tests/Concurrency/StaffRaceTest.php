<?php

use App\Actions\Staff\ManageStaffAccess;
use App\Enums\InvitationPurpose;
use App\Enums\Role;
use App\Models\RoleAssignment;
use App\Models\StaffInvitation;
use App\Models\StaffRecoveryCode;
use App\Models\User;
use App\Services\Staff\RecoveryCodes;
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
    config(['database.connections.observer' => config('database.connections.pgsql')]);
});

/**
 * @return array{0: resource, 1: resource}
 */
function startWorker(string $action, string|int ...$arguments): array
{
    $process = proc_open(
        [PHP_BINARY, base_path('tests/Concurrency/worker.php'), $action, ...array_map(strval(...), $arguments)],
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

/**
 * @param  list<array{0: resource, 1: resource}>  $workers
 * @return list<string>
 */
function releaseAndCollect(array $workers): array
{
    waitForBlockedWorkers(count($workers));
    DB::rollBack();

    $results = [];

    foreach ($workers as [$process, $output]) {
        $results[] = trim((string) stream_get_contents($output));
        proc_close($process);
    }

    sort($results);

    return $results;
}

it('keeps an administrator when two act on each other at once', function (string $first, string $second) {
    $one = staffWith(Role::Administrator);
    $two = staffWith(Role::Administrator);

    DB::beginTransaction();
    DB::table('users')->whereIn('id', [$one->id, $two->id])->lockForUpdate()->get();

    $results = releaseAndCollect([startWorker($first, $one->id, $two->id), startWorker($second, $two->id, $one->id)]);

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

it('spends a one-time link once when two use it at the same moment', function () {
    $staff = staffWith(Role::Reviewer);
    $url = app(ManageStaffAccess::class)->issueInvitation($staff, InvitationPurpose::Activate, null);
    $token = substr($url, strpos($url, '#') + 1);

    DB::beginTransaction();
    DB::table((new StaffInvitation)->getTable())->lockForUpdate()->get();

    expect(releaseAndCollect([startWorker('accept', $token), startWorker('accept', $token)]))->toBe(['ok', 'rejected']);
});

it('spends a recovery code once when two use it at the same moment', function () {
    $staff = staffWith(Role::Reviewer);
    $code = app(RecoveryCodes::class)->issueOnce($staff)[0];

    DB::beginTransaction();
    DB::table((new StaffRecoveryCode)->getTable())->lockForUpdate()->get();

    expect(releaseAndCollect([startWorker('recovery', $staff->id, $code), startWorker('recovery', $staff->id, $code)]))->toBe(['ok', 'rejected']);
});
