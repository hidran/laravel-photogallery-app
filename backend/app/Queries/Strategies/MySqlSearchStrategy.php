<?php

declare(strict_types=1);

namespace App\Queries\Strategies;

use App\Contracts\SearchStrategy;
use Illuminate\Database\Eloquent\Builder;

/**
 * MySQL FULLTEXT search using MATCH ... AGAINST in boolean mode.
 */
final class MySqlSearchStrategy implements SearchStrategy
{
    public function apply(Builder $query, string $term): void
    {
        // Strip FULLTEXT boolean operators to prevent resource abuse
        // (e.g. wildcard scans via `*` or forced term exclusion via `-`).
        $sanitized = preg_replace('/[+\-><()\~\*"@]+/', ' ', $term);

        $query->whereRaw(
            'MATCH(title, description) AGAINST (? IN BOOLEAN MODE)',
            [trim((string) $sanitized)]
        );
    }
}
