<?php

declare(strict_types=1);

namespace App\Http\Requests\Photo;

use App\Enums\TokenAbility;
use Illuminate\Foundation\Http\FormRequest;

final class UnfavoriteBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->tokenCan(TokenAbility::PhotosWrite->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo_ids' => ['required_without:all', 'array', 'min:1', 'max:500'],
            'photo_ids.*' => ['uuid'],
            'all' => ['required_without:photo_ids', 'boolean'],
        ];
    }
}
