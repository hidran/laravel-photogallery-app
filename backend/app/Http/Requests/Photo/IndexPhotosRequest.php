<?php

declare(strict_types=1);

namespace App\Http\Requests\Photo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DESIGN.md §6.2 — GET /photos query rules.
 */
final class IndexPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:1', 'max:100'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'exists:tags,slug'],
            'album_id' => ['nullable', 'uuid', 'exists:albums,id'],
            'favorites' => ['nullable', 'in:0,1'],
            'sort' => ['nullable', 'in:created_at,title,favorites'],
            'order' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
