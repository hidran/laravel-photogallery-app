<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Command DTO for the update photo action.
 */
final readonly class UpdatePhotoCommand
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $tagSlugs
     * @param  list<string>|null  $newTagNames
     */
    public function __construct(
        public array $data,
        public ?array $tagSlugs = null,
        public ?array $newTagNames = null,
    ) {}
}
