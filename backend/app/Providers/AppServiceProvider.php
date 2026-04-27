<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Photo;
use App\Observers\PhotoObserver;
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
    }
}
