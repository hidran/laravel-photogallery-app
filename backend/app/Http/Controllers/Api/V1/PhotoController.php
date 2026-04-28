<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Photo\IndexPhotosRequest;
use App\Http\Requests\Photo\UpdatePhotoRequest;
use App\Http\Resources\PhotoData;
use App\Models\Photo;
use App\Models\Tag;
use App\Queries\PhotoQuery;
use App\Services\TagAssigner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * DESIGN.md §6.2 — read endpoints. Mutating actions land in T032/T033/T034
 * once the upload pipeline + favorite endpoints are wired.
 */
final class PhotoController extends Controller
{
    public function index(IndexPhotosRequest $request): AnonymousResourceCollection
    {
        $page = PhotoQuery::for(Photo::query()->with(PhotoData::WITH))
            ->withSearch($request->validated('search'))
            ->withTags($request->validated('tags'))
            ->withAlbum($request->validated('album_id'))
            ->withFavorites($request->boolean('favorites'))
            ->applySort(
                $request->validated('sort'),
                $request->validated('order'),
            )
            ->paginate(
                $request->validated('cursor'),
                (int) $request->validated('per_page', 24),
            );

        return PhotoData::collection($page);
    }

    public function show(Photo $photo): JsonResponse
    {
        $photo->load(PhotoData::WITH);

        return PhotoData::make($photo)->response();
    }

    public function update(UpdatePhotoRequest $request, Photo $photo, TagAssigner $tagAssigner): JsonResponse
    {
        $this->ensureCan($request, 'update', $photo, TokenAbility::PhotosWrite);

        $data = $request->validated();
        $tags = $data['tags'] ?? null;
        $newTags = $data['new_tags'] ?? null;
        unset($data['tags'], $data['new_tags']);

        DB::transaction(function () use ($photo, $data, $tags, $newTags, $tagAssigner): void {
            $rowChanged = false;
            if ($data !== []) {
                $photo->update($data);
                $rowChanged = true;
            }

            if ($tags !== null || $newTags !== null) {
                // Single query — slugs come pre-validated by UpdatePhotoRequest's
                // `exists:tags,slug` rule, so a missing slug here is impossible.
                // Skipping the per-slug fallback closes the silent-create
                // edge case (PR #4 review C1).
                $existingNames = $tags
                    ? Tag::query()->whereIn('slug', $tags)->pluck('name')->all()
                    : [];

                $names = collect($existingNames)
                    ->merge($newTags ?? [])
                    ->unique()
                    ->values()
                    ->all();

                $tagAssigner->syncByNames($photo, $names);

                // The pivot changed but Eloquent doesn't bump updated_at on
                // pivot writes. Touching the photo invalidates the ETag so
                // a refetch sees the new tag set (PR #4 review C2).
                if (! $rowChanged) {
                    $photo->touch();
                }
            }
        });

        return PhotoData::make($photo->fresh(PhotoData::WITH))->response();
    }

    public function destroy(Request $request, Photo $photo): Response
    {
        $this->ensureCan($request, 'delete', $photo, TokenAbility::PhotosWrite);

        $photo->delete();

        return response()->noContent();
    }

    /**
     * PUT /photos/{photo}/favorite — idempotent. Always returns 204
     * (DESIGN.md §6.2 favorite block) regardless of prior state.
     */
    public function favorite(Request $request, Photo $photo): Response
    {
        $this->ensureCan($request, 'update', $photo, TokenAbility::PhotosWrite);

        if (! $photo->is_favorite) {
            $photo->forceFill(['is_favorite' => true])->save();
        }

        return response()->noContent();
    }

    /**
     * DELETE /photos/{photo}/favorite — idempotent.
     */
    public function unfavorite(Request $request, Photo $photo): Response
    {
        $this->ensureCan($request, 'update', $photo, TokenAbility::PhotosWrite);

        if ($photo->is_favorite) {
            $photo->forceFill(['is_favorite' => false])->save();
        }

        return response()->noContent();
    }

    /**
     * Combine policy + token-ability gate (CLAUDE.md rule 10). Throws the
     * same AuthorizationException either way so the universal-envelope
     * 403 fires from bootstrap/app.php.
     */
    private function ensureCan(Request $request, string $action, Photo $photo, TokenAbility $required): void
    {
        if (! $request->user()?->tokenCan($required->value)) {
            throw new AuthorizationException;
        }
        if ($request->user()?->cannot($action, $photo)) {
            throw new AuthorizationException;
        }
    }
}
