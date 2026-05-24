<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface SearchStrategy
{
    /**
     * Apply a search filter to the given query builder.
     */
    public function apply(Builder $query, string $term): void;
}
