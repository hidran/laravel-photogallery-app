<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * Apply to any Eloquent model that uses CHAR(36) UUID v7 primary keys.
 *
 * v7 ids are time-sortable, which is what cursor pagination relies on.
 */
trait HasUuidV7
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
}
