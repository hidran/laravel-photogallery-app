<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Photo;
use App\Models\User;
use App\Observers\PhotoObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Photo::observe(PhotoObserver::class);

        // Admin bypass for every policy (DESIGN.md §10.3). Returning null
        // (rather than false) lets non-admin users fall through to the
        // policy method's own logic.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);
    }
}
