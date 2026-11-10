<?php

namespace App\Enums;

/**
 * Why a one-time staff link was issued.
 */
enum InvitationPurpose: string
{
    case Activate = 'activate';
    case ResetPassword = 'reset_password';
}
