<?php

declare(strict_types=1);

namespace App\Queries\Strategies;

use App\Contracts\SearchStrategy;
use Illuminate\Database\Eloquent\Builder;

/**
 * SQLite LIKE fallback search with escaped wildcards.
 */
final class SqliteSearchStrategy implements SearchStrategy
{
    public function apply(Builder $query, string $term): void
    {
        $needle = '%'.addcslashes($term, '%_').'%';

        $query->where(function (Builder $q) use ($needle): void {
            $q->where('title', 'like', $needle)
                ->orWhere('description', 'like', $needle);
        });
    }
}
