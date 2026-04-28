<?php

declare(strict_types=1);

namespace App\Http\Requests\Album;

use App\Models\Photo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DESIGN.md §6.3 — POST /albums.
 *
 * `name` is unique-per-user (matches the composite UNIQUE on the column).
 * `cover_photo_id` must reference a photo owned by the authed user.
 */
final class StoreAlbumRequest extends FormRequest
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

        return [
            'name' => [
                'required', 'string', 'min:1', 'max:255',
                Rule::unique('albums', 'name')->where('user_id', $userId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_photo_id' => [
                'nullable', 'uuid',
                Rule::exists(Photo::class, 'id')->where('user_id', $userId),
            ],
        ];
    }
}
