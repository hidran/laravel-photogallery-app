<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Photo;

interface ImageProcessor
{
    /**
     * Generate thumbnail (300px), medium (800px), large (1600px) JPEGs.
     * Reads $photo->original_path from the photos_private disk; writes to photos disk.
     * Updates $photo->{thumbnail_path,medium_path,large_path,width,height}; saves.
     * Auto-orients from EXIF; never upscales; strips metadata from output.
     */
    public function generate(Photo $photo): void;
}
