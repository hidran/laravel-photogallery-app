<?php

declare(strict_types=1);

namespace App\Actions\Photo;

use App\Models\Photo;
use App\Models\Tag;
use App\Services\TagAssigner;
use Illuminate\Support\Facades\DB;

/**
 * DESIGN.md §6.2 — Photo update action.
 *
 * Handles field updates + tag sync inside a single transaction.
 * Touches the photo when only pivot data changes so the ETag
 * invalidates correctly.
 */
final class UpdatePhotoAction
{
    public function __construct(
        private readonly TagAssigner $tagAssigner,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated fields (title, description, album_id).
     * @param  list<string>|null  $tagSlugs  Existing tag slugs to sync.
     * @param  list<string>|null  $newTagNames  New tag names to create and attach.
     */
    public function __invoke(
        Photo $photo,
        array $data,
        ?array $tagSlugs,
        ?array $newTagNames,
    ): Photo {
        DB::transaction(function () use ($photo, $data, $tagSlugs, $newTagNames): void {
            $rowChanged = false;

            if ($data !== []) {
                $photo->update($data);
                $rowChanged = true;
            }

            if ($tagSlugs !== null || $newTagNames !== null) {
                $existingNames = $tagSlugs
                    ? Tag::query()->whereIn('slug', $tagSlugs)->pluck('name')->all()
                    : [];

                $names = collect($existingNames)
                    ->merge($newTagNames ?? [])
                    ->unique()
                    ->values()
                    ->all();

                $this->tagAssigner->syncByNames($photo, $names);

                // Pivot changes don't bump updated_at. Touch so the ETag
                // invalidates and clients see the new tag set.
                if (! $rowChanged) {
                    $photo->touch();
                }
            }
        });

        return $photo;
    }
}
