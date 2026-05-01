<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Photo\UploadPhotosAction;
use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Photo\IndexPhotosRequest;
use App\Http\Requests\Photo\StorePhotosRequest;
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
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DESIGN.md §6.2 — Photo CRUD + favorites.
 */
final class PhotoController extends Controller
{
    /**
     * POST /photos — upload 1-20 photos, dispatch ProcessPhoto batch.
     *
     * Returns 202 Accepted with a Location header pointing to the batch
     * status endpoint so the client can poll for processing completion
     * (DESIGN.md §6.2, §12.2).
     */
    public function store(StorePhotosRequest $request, UploadPhotosAction $action): JsonResponse
    {
        $this->ensureCan($request, 'create', Photo::class, TokenAbility::PhotosWrite);

        $validated = $request->validated();

        // Resolve tag names from existing slugs + new tag names.
        $existingNames = ! empty($validated['tags'])
            ? Tag::query()->whereIn('slug', $validated['tags'])->pluck('name')->all()
            : [];

        $tagNames = collect($existingNames)
            ->merge($validated['new_tags'] ?? [])
            ->unique()
            ->values()
            ->all();

        $result = $action(
            files: $validated['files'],
            user: $request->user(),
            albumId: $validated['album_id'] ?? null,
            tagNames: $tagNames,
            titles: $validated['titles'] ?? [],
            description: $validated['description'] ?? null,
        );

        // Load relations for the response.
        $photos = collect($result['photos'])->each(fn (Photo $p) => $p->load(PhotoData::WITH));

        return response()->json([
            'data' => [
                'batch_id' => $result['batch_id'],
                'total' => $result['total'],
                'photos' => PhotoData::collection($photos),
            ],
        ], 202)->header(
            'Location',
            url("/api/v1/photos/batch/{$result['batch_id']}"),
        );
    }

    public function index(IndexPhotosRequest $request): AnonymousResourceCollection
    {
        // Resolve user via Sanctum guard directly — public route has no
        // auth:sanctum middleware, so $request->user() is null even with
        // a valid token. auth('sanctum')->user() resolves it optionally.
        $user = auth('sanctum')->user();

        $page = PhotoQuery::for(Photo::query()->with(PhotoData::WITH)->withCount(PhotoData::WITH_COUNT))
            ->withSearch($request->validated('search'))
            ->withTags($request->validated('tags'))
            ->withAlbum($request->validated('album_id'))
            ->withFavorites($request->boolean('favorites'), $user?->id)
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
        $photo->loadCount(PhotoData::WITH_COUNT);

        return PhotoData::make($photo)->response();
    }

    /**
     * GET /photos/{photo}/original — serve the original file from the private disk.
     * Protected by signed URL + auth:sanctum + ownership/admin check.
     */
    public function original(Request $request, Photo $photo): StreamedResponse
    {
        if ($request->user()?->cannot('view', $photo)) {
            throw new AuthorizationException;
        }

        if (! $photo->original_path || ! Storage::disk('photos_private')->exists($photo->original_path)) {
            abort(404);
        }

        return Storage::disk('photos_private')->download(
            $photo->original_path,
            $photo->filename,
            ['Content-Type' => $photo->mime_type],
        );
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
     *
     * Any authenticated user can favorite any photo — no ownership check.
     */
    public function favorite(Request $request, Photo $photo): Response
    {
        if (! $request->user()?->tokenCan(TokenAbility::PhotosWrite->value)) {
            throw new AuthorizationException;
        }

        $request->user()->favoritePhotos()->syncWithoutDetaching([$photo->id]);

        return response()->noContent();
    }

    /**
     * DELETE /photos/{photo}/favorite — idempotent.
     *
     * Any authenticated user can unfavorite any photo — no ownership check.
     */
    public function unfavorite(Request $request, Photo $photo): Response
    {
        if (! $request->user()?->tokenCan(TokenAbility::PhotosWrite->value)) {
            throw new AuthorizationException;
        }

        $request->user()->favoritePhotos()->detach($photo->id);

        return response()->noContent();
    }

    /**
     * DELETE /photos/favorites/batch — unfavorite multiple photos at once.
     *
     * Body: { "photo_ids": ["uuid", ...] } or { "all": true }
     */
    public function unfavoriteBatch(Request $request): Response
    {
        if (! $request->user()?->tokenCan(TokenAbility::PhotosWrite->value)) {
            throw new AuthorizationException;
        }

        if ($request->boolean('all')) {
            $request->user()->favoritePhotos()->detach();
        } else {
            $validated = $request->validate([
                'photo_ids' => ['required', 'array', 'min:1'],
                'photo_ids.*' => ['uuid'],
            ]);
            $request->user()->favoritePhotos()->detach($validated['photo_ids']);
        }

        return response()->noContent();
    }

    /**
     * Combine policy + token-ability gate (CLAUDE.md rule 10). Throws the
     * same AuthorizationException either way so the universal-envelope
     * 403 fires from bootstrap/app.php.
     *
     * @param  Photo|class-string<Photo>  $target
     */
    private function ensureCan(Request $request, string $action, Photo|string $target, TokenAbility $required): void
    {
        if (! $request->user()?->tokenCan($required->value)) {
            throw new AuthorizationException;
        }
        if ($request->user()?->cannot($action, $target)) {
            throw new AuthorizationException;
        }
    }
}
