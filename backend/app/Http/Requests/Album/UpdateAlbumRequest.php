<?php

declare(strict_types=1);

namespace App\Http\Requests\Album;

use App\Models\Album;
use App\Models\Photo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DESIGN.md §6.3 — PATCH /albums/{album}.
 *
 * The unique check ignores the current row so renaming an album to its
 * existing name doesn't trip 422.
 */
final class UpdateAlbumRequest extends FormRequest
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
        $userId = $this->user()?->id;

        // The route binds the album to {album}; resolve it for ignore().
        $album = $this->route('album');
        $albumId = $album instanceof Album ? $album->id : (is_string($album) ? $album : null);

        return [
            'name' => [
                'sometimes', 'required', 'string', 'min:1', 'max:255',
                Rule::unique('albums', 'name')
                    ->where('user_id', $userId)
                    ->ignore($albumId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_photo_id' => [
                'nullable', 'uuid',
                Rule::exists(Photo::class, 'id')->where('user_id', $userId),
            ],
        ];
    }
}
