<?php

declare(strict_types=1);

namespace App\Http\Requests\Album;

use Illuminate\Foundation\Http\FormRequest;

final class IndexAlbumsRequest extends FormRequest
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
            'sort' => ['sometimes', 'string', 'in:newest,name_asc,most_photos'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
