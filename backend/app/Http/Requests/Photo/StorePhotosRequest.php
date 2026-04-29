<?php

declare(strict_types=1);

namespace App\Http\Requests\Photo;

use App\Models\Album;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DESIGN.md §6.2 — POST /photos.
 *
 * `files|array|min:1|max:20`, each `image|mimes:jpg,jpeg,png,webp|max:10240`.
 * Authorization (own the album) is delegated to the controller's
 * authorize() call against AlbumPolicy@update; we just enforce the album
 * exists and is owned by the authenticated user via Rule::exists scoped
 * to user_id, which produces a clean 422 instead of a controller 403.
 */
final class StorePhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'titles' => ['nullable', 'array'],
            'titles.*' => ['string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'album_id' => [
                'nullable',
                'uuid',
                Rule::exists(Album::class, 'id')->where('user_id', $this->user()?->id),
            ],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'exists:tags,slug'],
            'new_tags' => ['nullable', 'array', 'max:20'],
            'new_tags.*' => ['string', 'min:1', 'max:100', 'regex:/^[\w\s\-\.]+$/u'],
            'is_favorite' => ['nullable', 'in:0,1'],
        ];
    }
}
