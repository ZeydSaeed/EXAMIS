<?php

namespace App\Console\Commands;

use App\Actions\Staff\ManageStaffAccess;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Break-glass recovery for when no administrator can sign in. Needs shell
 * access to the server, clears the account's two-factor setup, and prints a
 * one-time link to set a new password. Every use is audited.
 */
class RecoverAdministrator extends Command
{
    protected $signature = 'examis:recover-admin {email : Sign-in email of the administrator}';

    protected $description = 'Reset an administrator\'s two-factor setup and print a one-time password link';

    public function handle(ManageStaffAccess $access): int
    {
        $email = $this->argument('email');
        $user = User::query()->staff()->whereRaw('lower(email) = ?', [strtolower(trim(is_string($email) ? $email : ''))])->first();

        if ($user === null || ! $user->isActive() || ! $user->hasRole(Role::Administrator)) {
            $this->error('No active administrator has this email.');

            return self::FAILURE;
        }

        if (! $this->confirm("Reset two-factor authentication for {$user->email}?")) {
            return self::SUCCESS;
        }

        $this->line($access->recover($user));

        return self::SUCCESS;
    }
}
