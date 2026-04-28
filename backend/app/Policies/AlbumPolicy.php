<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Album;
use App\Models\User;

/**
 * DESIGN.md §6.4 / §10.3 — ownership rules for album mutations.
 * Admin bypass via Gate::before in AppServiceProvider::boot().
 */
final class AlbumPolicy
{
    public function view(User $user, Album $album): bool
    {
        return $album->user_id === $user->id;
    }

    public function update(User $user, Album $album): bool
    {
        return $album->user_id === $user->id;
    }

    public function delete(User $user, Album $album): bool
    {
        return $album->user_id === $user->id;
    }
}
