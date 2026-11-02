<?php

namespace App\Http\Requests\Auth;

use App\Services\Staff\RecoveryCodes;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

/**
 * Checks recovery codes against the hashed staff set instead of Fortify's
 * decryptable list. The code is spent here, so what this returns only tells
 * Fortify's controller that one was valid.
 */
class StaffTwoFactorLoginRequest extends TwoFactorLoginRequest
{
    public function validRecoveryCode()
    {
        if (! $this->recovery_code || ! app(RecoveryCodes::class)->consume($this->challengedUser(), (string) $this->recovery_code)) {
            return null;
        }

        $this->session()->forget('login.id');

        return 'consumed';
    }
}
