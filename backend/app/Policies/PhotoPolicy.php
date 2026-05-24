<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;
use App\Policies\Concerns\OwnsResource;

/**
 * DESIGN.md §6.4 / §10.3 — ownership rules for photo mutations and the
 * gated `urls.original` field on PhotoData. Admin bypass lives in
 * AppServiceProvider::boot() as a Gate::before callback so it covers
 * both photo and album policies in one place.
 */
final class PhotoPolicy
{
    use OwnsResource;

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Photo $photo): bool
    {
        return $this->ownerMatches($user, $photo);
    }

    public function update(User $user, Photo $photo): bool
    {
        return $this->ownerMatches($user, $photo);
    }

    public function delete(User $user, Photo $photo): bool
    {
        return $this->ownerMatches($user, $photo);
    }
}
