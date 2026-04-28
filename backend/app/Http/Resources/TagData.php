<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DESIGN.md §6.6 — TagData. photos_count is hydrated by ->withCount('photos')
 * on the source query (TagController@index does this).
 *
 * @mixin Tag
 */
final class TagData extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'photos_count' => $this->whenCounted('photos', default: 0),
        ];
    }
}
