<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagData;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * DESIGN.md §6.4 — GET /tags. Not paginated — the global vocabulary
 * is small enough for v1 that returning everything in one hit is the
 * KISS choice. If it grows, we add cursor pagination later.
 */
final class TagController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sort = $request->query('sort', 'count_desc');

        $query = Tag::query()->withCount('photos');

        match ($sort) {
            'name_asc' => $query->orderBy('name'),
            default => $query->orderBy('photos_count', 'desc')->orderBy('name'),
        };

        return TagData::collection($query->get());
    }
}
