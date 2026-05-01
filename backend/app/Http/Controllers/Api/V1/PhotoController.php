<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Photo\UpdatePhotoAction;
use App\Actions\Photo\UploadPhotosAction;
use App\DTOs\UpdatePhotoCommand;
use App\DTOs\UploadPhotosCommand;
use App\Enums\TokenAbility;
use App\Http\Controllers\Concerns\AuthorizesWithToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Photo\IndexPhotosRequest;
use App\Http\Requests\Photo\StorePhotosRequest;
use App\Http\Requests\Photo\UnfavoriteBatchRequest;
use App\Http\Requests\Photo\UpdatePhotoRequest;
use App\Http\Resources\PhotoData;
use App\Models\Photo;
use App\Models\Tag;
use App\Queries\PhotoQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DESIGN.md §6.2 — Photo CRUD + favorites.
 */
final class PhotoController extends Controller
{
    use AuthorizesWithToken;
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

        $result = $action(new UploadPhotosCommand(
            files: $validated['files'],
            user: $request->user(),
            albumId: $validated['album_id'] ?? null,
            tagNames: $tagNames,
            titles: $validated['titles'] ?? [],
            description: $validated['description'] ?? null,
        ));

        // Batch-load relations in a single query per relation instead of N+1.
        // Use Eloquent\Collection (not Support\Collection) — only the former has load().
        $photos = new \Illuminate\Database\Eloquent\Collection($result['photos']);
        $photos->load(PhotoData::WITH);

        return response()->json([
            'data' => [
                'batch_id' => $result['batch_id'],
                'total' => $result['total'],
                'photos' => PhotoData::collection($photos),
            ],
        ], 202)->header(
            'Location',
            route('api.v1.photos.batch', $result['batch_id']),
        );
    }

    public function index(IndexPhotosRequest $request): AnonymousResourceCollection
    {
        // Resolve user via Sanctum guard directly — public route has no
        // auth:sanctum middleware, so $request->user() is null even with
        // a valid token. auth('sanctum')->user() resolves it optionally.
        $user = auth('sanctum')->user();

        $query = Photo::query()->with(PhotoData::WITH)->withCount(PhotoData::WITH_COUNT);

        // Only load favoritedBy rows for the current viewer — prevents loading
        // thousands of favorites per photo just to check is_favorite.
        if ($user !== null) {
            $query->with([
                'favoritedBy' => fn ($q) => $q->where('users.id', $user->id),
            ]);
        }

        $page = PhotoQuery::for($query)
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

        // Whitelist Content-Type to prevent serving polyglot files (e.g. SVG
        // with embedded scripts) with a dangerous MIME type.
        $safeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $contentType = in_array($photo->mime_type, $safeTypes, true)
            ? $photo->mime_type
            : 'application/octet-stream';

        return response()->streamDownload(
            function () use ($photo) {
                $stream = Storage::disk('photos_private')->readStream($photo->original_path);
                if ($stream === false) {
                    abort(500, 'Unable to read original file.');
                }
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            $photo->filename,
            ['Content-Type' => $contentType],
        );
    }

    public function update(UpdatePhotoRequest $request, Photo $photo, UpdatePhotoAction $action): JsonResponse
    {
        $this->ensureCan($request, 'update', $photo, TokenAbility::PhotosWrite);

        $data = $request->validated();
        $tagSlugs = $data['tags'] ?? null;
        $newTags = $data['new_tags'] ?? null;
        unset($data['tags'], $data['new_tags']);

        $action($photo, new UpdatePhotoCommand(
            data: $data,
            tagSlugs: $tagSlugs,
            newTagNames: $newTags,
        ));

        return PhotoData::make($photo->fresh(PhotoData::WITH))->response();
    }

    public function destroy(Request $request, Photo $photo): Response
    {
        $this->ensureCan($request, 'delete', $photo, TokenAbility::PhotosWrite);

        $photo->delete();

        return response()->noContent();
    }

    /**
     * DELETE /photos/batch — delete multiple photos owned by the current user.
     *
     * Body: { "photo_ids": ["uuid", ...] }
     */
    public function destroyBatch(Request $request): Response
    {
        $this->ensureTokenAbility($request, TokenAbility::PhotosWrite);

        $validated = $request->validate([
            'photo_ids' => ['required', 'array', 'min:1', 'max:500'],
            'photo_ids.*' => ['uuid'],
        ]);

        $photos = Photo::query()
            ->whereIn('id', $validated['photo_ids'])
            ->where('user_id', $request->user()->id)
            ->get();

        foreach ($photos as $photo) {
            $photo->delete();
        }

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
        $this->ensureTokenAbility($request, TokenAbility::PhotosWrite);

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
        $this->ensureTokenAbility($request, TokenAbility::PhotosWrite);

        $request->user()->favoritePhotos()->detach($photo->id);

        return response()->noContent();
    }

    /**
     * DELETE /photos/favorites/batch — unfavorite multiple photos at once.
     *
     * Body: { "photo_ids": ["uuid", ...] } or { "all": true }
     */
    public function unfavoriteBatch(UnfavoriteBatchRequest $request): Response
    {
        $validated = $request->validated();

        if (! empty($validated['all'])) {
            $request->user()->favoritePhotos()->detach();
        } else {
            $request->user()->favoritePhotos()->detach($validated['photo_ids']);
        }

        return response()->noContent();
    }


}
