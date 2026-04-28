<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Photo\IndexPhotosRequest;
use App\Http\Resources\PhotoData;
use App\Models\Photo;
use App\Queries\PhotoQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
