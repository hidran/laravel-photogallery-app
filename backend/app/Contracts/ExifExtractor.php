<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface ExifExtractor
{
    /**
     * Returns sanitized EXIF (no GPS) from a local file path.
     * Returns [] on failure or missing EXIF.
     * Keys returned: camera, iso, aperture, shutter, focal_length, taken_at.
     *
     * @return array<string, mixed>
     */
    public function extract(string $absolutePath): array;

    /**
     * Returns a copy of $file with all GPS EXIF tags removed.
     * The returned UploadedFile is safe to persist as the public original-equivalent
     * (though originals still go to photos_private).
     */
    public function stripGps(UploadedFile $file): UploadedFile;
}
