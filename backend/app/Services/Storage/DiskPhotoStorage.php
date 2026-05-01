<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\PhotoStorage;
use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DiskPhotoStorage implements PhotoStorage
{
    /**
     * Persist the original upload on the private disk.
     *
     * @return string Relative path within photos_private disk
     */
    public function storeOriginal(UploadedFile $file, string $photoId): string
    {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower($file->extension() ?: 'jpg');
        $extension = in_array($ext, $allowed, true) ? $ext : 'jpg';
        $relativePath = "originals/{$photoId}.{$extension}";

        Storage::disk('photos_private')->put(
            $relativePath,
            $file->getContent(),
        );

        return $relativePath;
    }

    /**
     * Persist a generated variant (thumbnail|medium|large) on the public disk.
     *
     * @return string Relative path within photos disk
     */
    public function storeVariant(string $photoId, string $variant, string $contents): string
    {
        $relativePath = "{$variant}/{$photoId}.jpg";

        Storage::disk('photos')->put($relativePath, $contents);

        return $relativePath;
    }

    /**
     * Full public URL for a variant on the photos disk.
     */
    public function publicVariantUrl(string $relativePath): string
    {
        return Storage::disk('photos')->url($relativePath);
    }

    /**
     * Time-limited signed URL for the original on photos_private.
     *
     * On local disk drivers that don't support temporaryUrl(), falls back to
     * the plain url() — acceptable for local development only.
     */
    public function signedOriginalUrl(Photo $photo, int $ttlSeconds = 300): string
    {
        $disk = Storage::disk('photos_private');

        try {
            return $disk->temporaryUrl(
                $photo->original_path,
                now()->addSeconds($ttlSeconds),
            );
        } catch (RuntimeException) {
            // Local disk driver does not support temporaryUrl().
            // Falling back to plain URL is acceptable in dev only.
            return $disk->url($photo->original_path);
        }
    }

    /**
     * Delete all files associated with a photo (original + 3 variants).
     *
     * Silently ignores missing files so callers don't need to guard.
     */
    public function purge(Photo $photo): void
    {
        if ($photo->original_path) {
            Storage::disk('photos_private')->delete($photo->original_path);
        }

        $variantPaths = array_filter([
            $photo->thumbnail_path,
            $photo->medium_path,
            $photo->large_path,
        ]);

        if ($variantPaths !== []) {
            Storage::disk('photos')->delete($variantPaths);
        }
    }
}
