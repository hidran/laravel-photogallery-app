<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Photo;
use App\Models\Tag;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Centralised tag-by-name upsert + photo sync (DESIGN.md §6.4 / REQUIREMENTS
 * §F7). Used by POST /photos, PATCH /photos/{id}, and the Filament
 * PhotoResource form — one implementation, three call sites (CLAUDE.md DRY).
 */
final class TagAssigner
{
    /**
     * Resolve each name into a Tag (creating missing rows) and replace the
     * photo's tag set with the resolved collection.
     *
     * Slug rules:
     *  - Generated via Str::slug($name).
     *  - If the slug is already taken by a DIFFERENT name, suffix with
     *    `-2`, `-3`, … until unique. The original name is preserved.
     *
     * @param  list<string>  $names
     */
    public function syncByNames(Photo $photo, array $names): void
    {
        DB::transaction(function () use ($photo, $names): void {
            $tagIds = [];

            foreach (array_filter(array_map('trim', $names)) as $name) {
                $tagIds[] = $this->resolveTag($name)->id;
            }

            $photo->tags()->sync($tagIds);
        });
    }

    private function resolveTag(string $name): Tag
    {
        // Match on name first (case-sensitively, since DESIGN.md doesn't
        // promise normalization). Two members typing "Sunset" and "sunset"
        // get separate tags by design — call sites that want collapsing
        // can lower-case before passing in.
        $existing = Tag::query()->where('name', $name)->first();
        if ($existing !== null) {
            return $existing;
        }

        $slug = $this->uniqueSlugFor($name);

        try {
            return Tag::create([
                'name' => $name,
                'slug' => $slug,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return Tag::where('slug', $slug)->firstOrFail();
            }

            throw $e;
        }
    }

    private function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'tag';
        }

        $candidate = $base;
        $counter = 2;

        while (Tag::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
