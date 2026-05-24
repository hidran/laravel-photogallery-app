<?php

declare(strict_types=1);

namespace App\Actions\Photo;

use App\DTOs\UpdatePhotoCommand;
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

    public function __invoke(Photo $photo, UpdatePhotoCommand $command): Photo
    {
        DB::transaction(function () use ($photo, $command): void {
            $rowChanged = false;

            if ($command->data !== []) {
                $photo->update($command->data);
                $rowChanged = true;
            }

            if ($command->tagSlugs !== null || $command->newTagNames !== null) {
                $existingNames = $command->tagSlugs
                    ? Tag::query()->whereIn('slug', $command->tagSlugs)->pluck('name')->all()
                    : [];

                $names = collect($existingNames)
                    ->merge($command->newTagNames ?? [])
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
