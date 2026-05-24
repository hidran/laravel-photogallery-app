<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface GpsStripper
{
    /**
     * Returns a copy of $file with all GPS EXIF tags removed.
     * The returned UploadedFile is safe to persist as the public original-equivalent
     * (though originals still go to photos_private).
     */
    public function stripGps(UploadedFile $file): UploadedFile;
}
