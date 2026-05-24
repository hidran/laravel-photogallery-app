<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Album;
use App\Models\User;
use App\Policies\Concerns\OwnsResource;

/**
 * DESIGN.md §6.4 / §10.3 — ownership rules for album mutations.
 * Admin bypass via Gate::before in AppServiceProvider::boot().
 */
final class AlbumPolicy
{
    use OwnsResource;

    /**
     * Any authenticated user with the albums:write ability can create
     * an album. Returning a Gate-friendly bool here so the dual-gate
     * pattern (token ability + policy) is symmetric across all album
     * mutations (PR #4 review S3).
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Album $album): bool
    {
        return $this->ownerMatches($user, $album);
    }

    public function update(User $user, Album $album): bool
    {
        return $this->ownerMatches($user, $album);
    }

    public function delete(User $user, Album $album): bool
    {
        return $this->ownerMatches($user, $album);
    }
}
