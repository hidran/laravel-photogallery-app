<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Album\StoreAlbumRequest;
use App\Http\Requests\Album\UpdateAlbumRequest;
use App\Http\Resources\AlbumData;
use App\Models\Album;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * DESIGN.md §6.3 — albums CRUD.
 */
final class AlbumController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sort = $request->query('sort', 'newest');
        $perPage = (int) $request->query('per_page', 24);
        $cursor = $request->query('cursor');

        $query = Album::query()
            ->with(AlbumData::WITH)
            ->withCount('photos');

        match ($sort) {
            'name_asc' => $query->orderBy('name', 'asc'),
            'most_photos' => $query->orderBy('photos_count', 'desc')->orderBy('created_at', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };
        $query->orderBy('id', 'desc');

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
        $this->ensureAbility($request, TokenAbility::AlbumsWrite);

        $album = Album::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $album->load(AlbumData::WITH)->loadCount('photos');

        return AlbumData::make($album)->response()->setStatusCode(201);
    }

    public function update(UpdateAlbumRequest $request, Album $album): JsonResponse
    {
        $this->ensureCan($request, 'update', $album);

        $album->update($request->validated());
        $album->load(AlbumData::WITH)->loadCount('photos');

        return AlbumData::make($album)->response();
    }

    public function destroy(Request $request, Album $album): Response
    {
        $this->ensureCan($request, 'delete', $album);

        $album->delete();

        return response()->noContent();
    }

    private function ensureAbility(Request $request, TokenAbility $required): void
    {
        if (! $request->user()?->tokenCan($required->value)) {
            throw new AuthorizationException;
        }
    }

    private function ensureCan(Request $request, string $action, Album $album): void
    {
        $this->ensureAbility($request, TokenAbility::AlbumsWrite);
        if ($request->user()?->cannot($action, $album)) {
            throw new AuthorizationException;
        }
    }
}
