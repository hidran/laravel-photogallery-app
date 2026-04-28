<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * DESIGN.md §6.6 — AlbumData.
 *
 * cover_photo is rendered as a minimal thumb-only object so the gallery's
 * sidebar and album-grid components can hydrate without a second hop;
 * full PhotoData lives behind the photos relation manager.
 *
 * @mixin Album
 */
final class AlbumData extends JsonResource
{
    /**
     * @var list<string>
     */
    public const array WITH = [
        'coverPhoto:id,thumbnail_path,user_id',
        'user:id,name',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'cover_photo' => $this->whenLoaded('coverPhoto', fn () => $this->coverPhoto ? [
                'id' => $this->coverPhoto->id,
                'urls' => array_filter([
                    'thumbnail' => $this->coverPhoto->thumbnail_path
                        ? Storage::disk('photos')->url($this->coverPhoto->thumbnail_path)
                        : null,
                ]),
            ] : null),
            'photos_count' => $this->whenCounted('photos', default: 0),
            'owner' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
