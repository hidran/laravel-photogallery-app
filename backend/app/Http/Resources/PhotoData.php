<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\ValueObjects\PhotoUrls;
use App\Models\Photo;
use App\Services\Authorization\PhotoFieldGate;
use App\Services\Url\PhotoUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DESIGN.md §6.6 — PhotoData.
 *
 * @mixin Photo
 */
final class PhotoData extends JsonResource
{
    /**
     * Eager-load list. Controllers reference this constant (CLAUDE.md DRY
     * rule — eager-loading lists are constants on the Resource).
     *
     * @var list<string>
     */
    public const array WITH = [
        'album:id,name',
        'tags:id,name,slug',
        'user:id,name',
        'favoritedBy:id',
    ];

    /**
     * Eager-load list with favorites count for list endpoints.
     *
     * @var list<string>
     */
    public const array WITH_COUNT = [
        'favoritedBy',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user() ?? auth('sanctum')->user();

        $fieldGate = new PhotoFieldGate;
        $isOwnerOrAdmin = $fieldGate->canViewSensitiveFields($viewer, $this->resource);
        $isCompleted = $this->processing_status?->value === 'completed';

        $urlGenerator = new PhotoUrlGenerator;
        $urls = new PhotoUrls($urlGenerator, $this->resource, $isOwnerOrAdmin);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'filename' => $this->filename,
            'urls' => $urls->toArray(),
            'width' => $isCompleted ? $this->width : null,
            'height' => $isCompleted ? $this->height : null,
            'file_size' => (int) $this->file_size,
            'mime_type' => $this->mime_type,
            'is_favorite' => $this->when(
                $viewer !== null,
                fn () => $this->relationLoaded('favoritedBy')
                    ? $this->favoritedBy->contains('id', $viewer?->id)
                    : false,
                false,
            ),
            'favorites_count' => $this->favorited_by_count ?? 0,
            // EXIF is safe for all users — GPS is stripped during processing.
            // processing_error may leak path fragments — owner+admin only.
            'exif' => $this->exif,
            'processing_status' => $this->processing_status?->value,
            'processing_error' => $isOwnerOrAdmin ? $this->processing_error : null,
            'album' => $this->whenLoaded('album', fn () => [
                'id' => $this->album->id,
                'name' => $this->album->name,
            ]),
            'tags' => TagData::collection($this->whenLoaded('tags')),
            'owner' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
