<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait OwnsResource
{
    /**
     * Determine whether the user owns the given model.
     */
    protected function ownerMatches(User $user, Model $model): bool
    {
        return $model->user_id === $user->id;
    }
}
