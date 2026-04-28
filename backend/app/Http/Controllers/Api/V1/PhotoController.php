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
            ->withFavorites((bool) $request->validated('favorites', false))
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
            if ($data !== []) {
                $photo->update($data);
            }

            if ($tags !== null || $newTags !== null) {
                // Resolve incoming slugs to names so TagAssigner can
                // upsert by name uniformly.
                $names = collect($tags ?? [])
                    ->map(fn (string $slug) => Tag::where('slug', $slug)->value('name') ?? $slug)
                    ->merge($newTags ?? [])
                    ->unique()
                    ->values()
                    ->all();

                $tagAssigner->syncByNames($photo, $names);
            }
        });

        $photo->load(PhotoData::WITH);

        return PhotoData::make($photo->fresh(PhotoData::WITH))->response();
    }

    public function destroy(Request $request, Photo $photo): Response
    {
        $this->ensureCan($request, 'delete', $photo, TokenAbility::PhotosWrite);

        $photo->delete();

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
