<?php

declare(strict_types=1);

namespace App\Http\Resources\ValueObjects;

use App\Models\Photo;
use App\Services\Url\PhotoUrlGenerator;

/**
 * Immutable value object representing all URLs for a photo.
 */
final readonly class PhotoUrls
{
    public function __construct(
        private PhotoUrlGenerator $generator,
        private Photo $photo,
        private bool $includeOriginal,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        $urls = [
            'thumbnail' => $this->generator->variantUrl($this->photo->thumbnail_path),
            'medium' => $this->generator->variantUrl($this->photo->medium_path),
            'large' => $this->generator->variantUrl($this->photo->large_path),
        ];

        if ($this->includeOriginal) {
            $urls['original'] = $this->generator->signedOriginalUrl($this->photo);
        }

        return array_filter($urls, fn ($v) => $v !== null);
    }
}
