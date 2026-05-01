<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
        $isOwnerOrAdmin = $viewer !== null && $viewer->can('view', $this->resource);
        $isCompleted = $this->processing_status?->value === 'completed';

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'filename' => $this->filename,
            'urls' => array_filter([
                'thumbnail' => $this->variantUrl($this->thumbnail_path),
                'medium' => $this->variantUrl($this->medium_path),
                'large' => $this->variantUrl($this->large_path),
                'original' => $isOwnerOrAdmin ? $this->signedOriginalUrl() : null,
            ], fn ($v) => $v !== null),
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

    private function variantUrl(?string $path): ?string
    {
        return $path ? Storage::disk('photos')->url($path) : null;
    }

    private function signedOriginalUrl(): ?string
    {
        if (! $this->original_path) {
            return null;
        }

        $ttl = (int) config('photogallery.urls.original_signed_ttl', 300);

        try {
            return Storage::disk('photos_private')->temporaryUrl(
                $this->original_path,
                now()->addSeconds($ttl)
            );
        } catch (\RuntimeException $e) {
            // Local disk doesn't support temporaryUrl — use a signed
            // Laravel route that serves the file through the controller.
            // Re-throw in production; fallback only acceptable in dev.
            if (app()->environment('production')) {
                throw $e;
            }

            return url()->signedRoute(
                'api.v1.photos.original',
                ['photo' => $this->id],
                now()->addSeconds($ttl),
            );
        }
    }
}
