<?php

namespace App\Console\Commands;

use App\Actions\Staff\ManageStaffAccess;
use App\Enums\InvitationPurpose;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates an administrator from the server, which is the only way to get the
 * first one. Each person runs through this or the staff screen separately, so
 * no account or secret is shared. The link is printed once and goes nowhere
 * else; the administrator then sets their own password and authenticator.
 */
class CreateAdministrator extends Command
{
    protected $signature = 'examis:create-admin {name : Full name} {email : Sign-in email}';

    protected $description = 'Create an administrator account and print its one-time activation link';

    public function handle(ManageStaffAccess $access): int
    {
        $email = Str::lower(trim($this->text('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('That is not a valid email address.');

            return self::INVALID;
        }

        if (User::query()->staff()->whereRaw('lower(email) = ?', [$email])->exists()) {
            $this->error('A staff account already uses this email.');

            return self::FAILURE;
        }

        $user = $access->create($this->text('name'), $email, [Role::Administrator], null);

        $this->info("Administrator {$email} created. Open this link once, within the validity period:");
        $this->line($access->issueInvitation($user, InvitationPurpose::Activate, null));

        return self::SUCCESS;
    }

    private function text(string $argument): string
    {
        $value = $this->argument($argument);

        return is_string($value) ? $value : '';
    }
}
