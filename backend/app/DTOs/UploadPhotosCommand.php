<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Command DTO for the upload photos action.
 */
final readonly class UploadPhotosCommand
{
    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $titles
     * @param  list<string>  $tagNames
     */
    public function __construct(
        public array $files,
        public User $user,
        public ?string $albumId,
        public array $tagNames,
        public array $titles = [],
        public ?string $description = null,
    ) {}
}
