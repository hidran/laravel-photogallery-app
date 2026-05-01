<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Photo;
use Illuminate\Support\Facades\Log;
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
        $paths = [
            ['disk' => 'photos_private', 'path' => $photo->original_path],
            ['disk' => 'photos', 'path' => $photo->thumbnail_path],
            ['disk' => 'photos', 'path' => $photo->medium_path],
            ['disk' => 'photos', 'path' => $photo->large_path],
        ];

        foreach ($paths as $item) {
            if ($item['path'] === null) {
                continue;
            }
            try {
                Storage::disk($item['disk'])->delete($item['path']);
            } catch (\Throwable $e) {
                Log::warning("Failed to delete file on {$item['disk']}: {$item['path']}", [
                    'photo_id' => $photo->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
