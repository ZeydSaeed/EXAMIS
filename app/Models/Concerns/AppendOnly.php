<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * History records can be inserted but never changed or removed through the
 * application. PostgreSQL enforces the same rule with a trigger.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function (): never {
            throw new LogicException(static::class.' records are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException(static::class.' records are append-only.');
        });
    }
}
