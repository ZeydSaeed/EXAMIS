<?php

namespace App\Enums;

/**
 * A document is downloadable only once it is Verified (scanned and checked).
 */
enum DocumentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
