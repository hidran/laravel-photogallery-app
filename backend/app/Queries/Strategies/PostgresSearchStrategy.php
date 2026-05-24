<?php

declare(strict_types=1);

namespace App\Queries\Strategies;

use App\Contracts\SearchStrategy;
use Illuminate\Database\Eloquent\Builder;

/**
 * PostgreSQL search using LIKE with escaped wildcards.
 *
 * Can be upgraded to tsvector/tsquery FULLTEXT when a GIN index is added
 * to the photos table (see migrations).
 */
final class PostgresSearchStrategy implements SearchStrategy
{
    public function apply(Builder $query, string $term): void
    {
        $needle = '%'.addcslashes($term, '%_').'%';

        $query->where(function (Builder $q) use ($needle): void {
            $q->where('title', 'ilike', $needle)
                ->orWhere('description', 'ilike', $needle);
        });
    }
}
