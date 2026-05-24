<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TokenAbility;
use App\Http\Controllers\Concerns\AuthorizesWithToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Album\IndexAlbumsRequest;
use App\Http\Requests\Album\StoreAlbumRequest;
use App\Http\Requests\Album\UpdateAlbumRequest;
use App\Http\Resources\AlbumData;
use App\Models\Album;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * DESIGN.md §6.3 — albums CRUD.
 */
final class AlbumController extends Controller
{
    use AuthorizesWithToken;

    public function index(IndexAlbumsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $sort = $validated['sort'] ?? 'newest';
        $perPage = (int) ($validated['per_page'] ?? 24);
        $cursor = $validated['cursor'] ?? null;

        $query = Album::query()
            ->with(AlbumData::WITH)
            ->withCount('photos');

        // Track the primary direction so the cursor tiebreaker on `id`
        // matches it — using a fixed `desc` here makes name_asc's
        // pagination non-deterministic across same-name rows
        // (PR #4 review C8).
        $direction = 'desc';
        match ($sort) {
            'name_asc' => [$query->orderBy('name', 'asc'), $direction = 'asc'],
            'most_photos' => $query->orderBy('photos_count', 'desc')->orderBy('created_at', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };
        $query->orderBy('id', $direction);

        return AlbumData::collection(
            $query->cursorPaginate($perPage, ['*'], 'cursor', $cursor)
        );
    }

    public function show(Album $album): JsonResponse
    {
        $album->load(AlbumData::WITH)->loadCount('photos');

        return AlbumData::make($album)->response();
    }

    public function store(StoreAlbumRequest $request): JsonResponse
    {
        // Dual gate (CLAUDE.md rule 10) — token ability AND policy. The
        // create() policy method is intentionally permissive but its
        // existence keeps every mutation symmetric and lets policies
        // tighten without controller edits (PR #4 review S3).
        $this->ensureCan($request, 'create', Album::class, TokenAbility::AlbumsWrite);

        $album = Album::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $album->load(AlbumData::WITH)->loadCount('photos');

        return AlbumData::make($album)->response()->setStatusCode(201);
    }

    public function update(UpdateAlbumRequest $request, Album $album): JsonResponse
    {
        $this->ensureCan($request, 'update', $album, TokenAbility::AlbumsWrite);

        $album->update($request->validated());
        $album->load(AlbumData::WITH)->loadCount('photos');

        return AlbumData::make($album)->response();
    }

    public function destroy(Request $request, Album $album): Response
    {
        $this->ensureCan($request, 'delete', $album, TokenAbility::AlbumsWrite);

        $album->delete();

        return response()->noContent();
    }
}
