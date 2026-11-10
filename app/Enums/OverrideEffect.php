<?php

namespace App\Enums;

/**
 * What a per-staff exception does to a permission its roles decide.
 */
enum OverrideEffect: string
{
    case Grant = 'grant';
    case Deny = 'deny';
}
