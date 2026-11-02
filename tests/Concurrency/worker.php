<?php

/*
 * One side of a race in this folder's tests, in its own process so it has
 * its own PostgreSQL connection. Prints "ok" or "rejected".
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

[, $action] = $argv;

$access = $app->make(ManageStaffAccess::class);

try {
    match ($action) {
        'demote' => $access->syncRoles(User::query()->findOrFail((int) $argv[3]), [], User::query()->findOrFail((int) $argv[2])),
        'deactivate' => $access->setActive(User::query()->findOrFail((int) $argv[3]), false, User::query()->findOrFail((int) $argv[2])),
        'accept' => $access->acceptInvitation($argv[2], 'Str0ng-Passw0rd!'),
    };

    echo 'ok';
} catch (DomainRuleViolation|AuthorizationException) {
    echo 'rejected';
}
