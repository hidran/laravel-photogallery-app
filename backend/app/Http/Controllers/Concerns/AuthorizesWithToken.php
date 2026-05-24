<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Enums\TokenAbility;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait AuthorizesWithToken
{
    /**
     * Ensure the authenticated user has the required token ability.
     *
     * @throws AuthorizationException
     */
    protected function ensureTokenAbility(Request $request, TokenAbility $required): void
    {
        if (! $request->user()?->tokenCan($required->value)) {
            throw new AuthorizationException;
        }
    }

    /**
     * Ensure the authenticated user has the required token ability
     * AND passes the given policy action for the target.
     *
     * @param  Model|class-string<Model>  $target
     *
     * @throws AuthorizationException
     */
    protected function ensureCan(Request $request, string $action, Model|string $target, TokenAbility $required): void
    {
        $this->ensureTokenAbility($request, $required);

        if ($request->user()?->cannot($action, $target)) {
            throw new AuthorizationException;
        }
    }
}
