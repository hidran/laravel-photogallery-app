<?php

declare(strict_types=1);

namespace App\Services\Url;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

/**
 * Generates public and signed URLs for photo variants and originals.
 */
final class PhotoUrlGenerator
{
    /**
     * Public URL for a variant on the photos disk.
     */
    public function variantUrl(?string $path): ?string
    {
        return $path ? Storage::disk('photos')->url($path) : null;
    }

    /**
     * Time-limited signed URL for the original on photos_private.
     *
     * On local disk drivers that don't support temporaryUrl(), falls back to
     * the plain url() — acceptable for local development only.
     */
    public function signedOriginalUrl(Photo $photo, ?int $ttlSeconds = null): ?string
    {
        if (! $photo->original_path) {
            return null;
        }

        $ttl = $ttlSeconds ?? (int) config('photogallery.urls.original_signed_ttl', 300);

        try {
            return Storage::disk('photos_private')->temporaryUrl(
                $photo->original_path,
                now()->addSeconds($ttl)
            );
        } catch (\RuntimeException $e) {
            // Local disk driver does not support temporaryUrl().
            // Re-throw in production; fallback only acceptable in dev.
            if (app()->environment('production')) {
                throw $e;
            }

            return url()->signedRoute(
                'api.v1.photos.original',
                ['photo' => $photo->id],
                now()->addSeconds($ttl)
            );
        }
    }
}
