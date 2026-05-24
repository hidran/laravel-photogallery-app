<?php

declare(strict_types=1);

namespace App\Contracts;

interface ExifReader
{
    /**
     * Returns sanitized EXIF (no GPS) from a local file path.
     * Returns [] on failure or missing EXIF.
     * Keys returned: camera, iso, aperture, shutter, focal_length, taken_at.
     *
     * @return array<string, mixed>
     */
    public function extract(string $absolutePath): array;
}
