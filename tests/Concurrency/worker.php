<?php

/*
 * One side of the race in LastAdministratorRaceTest, in its own process so it
 * has its own PostgreSQL connection. Prints "ok" or "rejected".
 */

use App\Actions\Staff\ManageStaffAccess;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::connection()->getDatabaseName() !== 'examis_testing') {
    fwrite(STDERR, 'Refusing to run outside examis_testing.');
    exit(1);
}

[, $action, $actorId, $targetId] = $argv;

$actor = User::query()->findOrFail((int) $actorId);
$target = User::query()->findOrFail((int) $targetId);
$access = $app->make(ManageStaffAccess::class);

try {
    match ($action) {
        'demote' => $access->syncRoles($target, [], $actor),
        'deactivate' => $access->setActive($target, false, $actor),
    };

    echo 'ok';
} catch (DomainRuleViolation|AuthorizationException) {
    echo 'rejected';
}
