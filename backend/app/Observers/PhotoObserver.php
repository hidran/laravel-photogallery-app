<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

final class PhotoObserver
{
    /**
     * On photo delete, remove the 4 files we may have written:
     *   - original on the photos_private disk
     *   - thumbnail/medium/large on the photos disk
     *
     * Each path may be null (a photo that failed before processing only
     * has the original); each disk-side file may be missing in test or
     * after a stale-row cleanup. We swallow either case.
     */
    public function deleted(Photo $photo): void
    {
        if ($photo->original_path) {
            Storage::disk('photos_private')->delete($photo->original_path);
        }

        $variants = array_filter([
            $photo->thumbnail_path,
            $photo->medium_path,
            $photo->large_path,
        ]);

        if ($variants) {
            Storage::disk('photos')->delete($variants);
        }
    }
}
