<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Photo;
use Illuminate\Http\UploadedFile;

interface PhotoStorage
{
    /** Persists the original on the private disk. Returns relative path. */
    public function storeOriginal(UploadedFile $file, string $photoId): string;

    /** Persists a generated variant (thumbnail|medium|large) on the public disk. Returns relative path. */
    public function storeVariant(string $photoId, string $variant, string $contents): string;

    /** Public URL for a variant on the photos disk. */
    public function publicVariantUrl(string $relativePath): string;

    /** Time-limited signed URL (5 min default) for the original on photos_private. */
    public function signedOriginalUrl(Photo $photo, int $ttlSeconds = 300): string;

    /** Deletes all 4 files associated with a photo, ignoring missing. */
    public function purge(Photo $photo): void;
}
