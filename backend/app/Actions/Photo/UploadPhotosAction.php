<?php

declare(strict_types=1);

namespace App\Actions\Photo;

use App\Contracts\ExifExtractor;
use App\Contracts\PhotoStorage;
use App\Enums\ProcessingStatus;
use App\Events\PhotoUploaded;
use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagAssigner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
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
        private readonly ExifExtractor $exif,
        private readonly TagAssigner $tagAssigner,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $titles  Per-file titles (index-matched); falls back to filename stem.
     * @param  list<string>  $tagNames  Tag names to sync onto every uploaded photo.
     * @return array{batch_id: string, total: int, photos: list<Photo>}
     */
    public function __invoke(
        array $files,
        User $user,
        ?string $albumId,
        array $tagNames,
        array $titles = [],
        ?string $description = null,
        bool $isFavorite = false,
    ): array {
        return DB::transaction(function () use ($files, $user, $albumId, $tagNames, $titles, $description, $isFavorite): array {
            $photos = [];
            $jobs = [];

            foreach ($files as $index => $file) {
                $sanitized = $this->exif->stripGps($file);
                $photoId = (string) Str::uuid7();
                $path = $this->storage->storeOriginal($sanitized, $photoId);

                $title = $titles[$index] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                $photo = Photo::create([
                    'id' => $photoId,
                    'user_id' => $user->id,
                    'album_id' => $albumId,
                    'title' => $title,
                    'description' => $description,
                    'filename' => $file->getClientOriginalName(),
                    'original_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'is_favorite' => $isFavorite,
                    'processing_status' => ProcessingStatus::Pending,
                ]);

                if ($tagNames !== []) {
                    $this->tagAssigner->syncByNames($photo, $tagNames);
                }

                event(new PhotoUploaded($photo));
                $photos[] = $photo;
                $jobs[] = new ProcessPhoto($photo);
            }

            $batch = Bus::batch($jobs)->dispatch();

            return [
                'batch_id' => $batch->id,
                'total' => count($photos),
                'photos' => $photos,
            ];
        });
    }
}
