<?php

declare(strict_types=1);

namespace App\Http\Requests\Photo;

use App\Models\Album;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DESIGN.md §6.2 — PATCH /photos/{photo}.
 *
 * Any subset is acceptable but at least one supported field must be
 * present (enforced via the `required_without_all` chain rather than a
 * single composite rule, so the 422 errors stay scoped to the field
 * the client tried to omit).
 */
final class UpdatePhotoRequest extends FormRequest
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
        $others = 'description,album_id,tags,new_tags';

        return [
            'title' => ['required_without_all:'.$others, 'string', 'min:1', 'max:255'],
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
        ];
    }
}
