<?php

declare(strict_types=1);

namespace App\Actions\Photo;

use App\Contracts\ExifReader;
use App\Contracts\GpsStripper;
use App\Contracts\PhotoStorage;
use App\DTOs\UploadPhotosCommand;
use App\Enums\ProcessingStatus;
use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use App\Services\TagAssigner;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DESIGN.md SS12.2 -- Upload pipeline action.
 *
 * Accepts 1-20 uploaded files, strips GPS EXIF, persists originals on the
 * private disk, creates Photo rows inside a transaction, syncs tags, fires
 * PhotoUploaded events, and dispatches a Bus::batch of ProcessPhoto jobs.
 *
 * Returns an array with batch_id, total count, and the created Photo models.
 */
final class UploadPhotosAction
{
    public function __construct(
        private readonly PhotoStorage $storage,
        private readonly ExifReader $exifReader,
        private readonly GpsStripper $gpsStripper,
        private readonly TagAssigner $tagAssigner,
    ) {}

    /**
     * @return array{batch_id: string, total: int, photos: list<Photo>}
     */
    public function __invoke(UploadPhotosCommand $command): array
    {
        // Phase 1: File I/O OUTSIDE the transaction to avoid holding a DB
        // connection open during heavy encoding/disk writes (issue #2).
        // Extract EXIF from the ORIGINAL file BEFORE stripGps re-encodes
        // and destroys all EXIF data (issue #1).
        $prepared = [];
        $storedPaths = [];
        foreach ($command->files as $index => $file) {
            $exifData = $this->exifReader->extract($file->getPathname());
            $sanitized = $this->gpsStripper->stripGps($file);
            $photoId = (string) Str::uuid7();
            $path = $this->storage->storeOriginal($sanitized, $photoId);
            $storedPaths[] = $path;

            $prepared[] = [
                'photoId' => $photoId,
                'path' => $path,
                'exif' => $exifData,
                'title' => $command->titles[$index] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'filename' => basename($file->getClientOriginalName()),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ];
        }

        try {
            // Phase 2: DB writes only — fast, no file I/O.
            [$photos, $jobs] = DB::transaction(function () use ($prepared, $command): array {
                $photos = [];
                $jobs = [];

                foreach ($prepared as $item) {
                    $photo = Photo::create([
                        'id' => $item['photoId'],
                        'user_id' => $command->user->id,
                        'album_id' => $command->albumId,
                        'title' => $item['title'],
                        'description' => $command->description,
                        'filename' => $item['filename'],
                        'original_path' => $item['path'],
                        'file_size' => $item['file_size'],
                        'mime_type' => $item['mime_type'],
                        'exif' => $item['exif'] !== [] ? $item['exif'] : null,
                        'processing_status' => ProcessingStatus::Pending,
                    ]);

                    if ($command->tagNames !== []) {
                        $this->tagAssigner->syncByNames($photo, $command->tagNames);
                    }

                    $photos[] = $photo;
                    $jobs[] = new ProcessPhoto($photo);
                }

                return [$photos, $jobs];
            });

            $batch = Bus::batch($jobs)->dispatch();

            Photo::whereIn('id', collect($photos)->pluck('id'))->update(['batch_id' => $batch->id]);
        } catch (\Throwable $e) {
            // Rollback orphaned originals if DB or batch dispatch failed.
            foreach ($storedPaths as $path) {
                Storage::disk('photos_private')->delete($path);
            }

            throw $e;
        }

        return [
            'batch_id' => $batch->id,
            'total' => count($photos),
            'photos' => $photos,
        ];
    }
}
