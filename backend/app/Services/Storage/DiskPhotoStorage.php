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

        $stream = fopen($file->getPathname(), 'r');
        if ($stream === false) {
            throw new RuntimeException("Cannot open upload for reading: {$file->getPathname()}");
        }

        try {
            Storage::disk('photos_private')->put($relativePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $relativePath;
    }

    /**
     * Persist a generated variant (thumbnail|medium|large) on the public disk.
     *
     * @param  string|resource  $contents  Binary string or open stream resource
     * @return string Relative path within photos disk
     */
    public function storeVariant(string $photoId, string $variant, mixed $contents): string
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
        } catch (RuntimeException $e) {
            // Local disk driver does not support temporaryUrl().
            // Re-throw in production; fallback only acceptable in dev.
            if (app()->environment('production')) {
                throw $e;
            }

            return $disk->url($photo->original_path);
        }
    }
}
