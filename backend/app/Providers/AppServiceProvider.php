<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ExifExtractor;
use App\Contracts\ImageProcessor;
use App\Contracts\PhotoStorage;
use App\Models\Photo;
use App\Models\User;
use App\Observers\PhotoObserver;
use App\Services\Imaging\InterventionImageProcessor;
use App\Services\Imaging\PhpExifExtractor;
use App\Services\Storage\DiskPhotoStorage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ImageProcessor::class, InterventionImageProcessor::class);
        $this->app->bind(ExifExtractor::class, PhpExifExtractor::class);
        $this->app->bind(PhotoStorage::class, DiskPhotoStorage::class);
    }

    public function boot(): void
    {
        // Force HTTPS for all generated URLs in production so signed URLs,
        // asset links, and route() calls never produce http:// links.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Photo::observe(PhotoObserver::class);

        // Admin bypass for every policy (DESIGN.md §10.3). Returning null
        // (rather than false) lets non-admin users fall through to the
        // policy method's own logic.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        $this->configureRateLimiters();
    }

    /**
     * DESIGN.md §6.0 rate limits.
     *
     *   /auth/* ≤ 10/min/IP
     *   everything else ≤ 120/min/IP AND ≤ 300/min/user
     *
     * The "api" limiter returns multiple Limit instances so the
     * throttle middleware enforces both ceilings simultaneously.
     */
    private function configureRateLimiters(): void
    {
        $authPerIp = (int) config('photogallery.rate_limits.auth_per_ip_per_minute', 10);
        $apiPerIp = (int) config('photogallery.rate_limits.api_per_ip_per_minute', 120);
        $apiPerUser = (int) config('photogallery.rate_limits.api_per_user_per_minute', 300);

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute($authPerIp)->by($request->ip()));

        RateLimiter::for('api', function (Request $request) use ($apiPerIp, $apiPerUser) {
            $limits = [Limit::perMinute($apiPerIp)->by($request->ip())];
            if ($request->user()) {
                $limits[] = Limit::perMinute($apiPerUser)->by('user:'.$request->user()->id);
            }

            return $limits;
        });
    }
}
