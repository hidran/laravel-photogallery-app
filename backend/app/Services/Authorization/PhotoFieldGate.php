<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\Photo;
use App\Models\User;

/**
 * Decides which fields a viewer can see on a Photo resource.
 */
final class PhotoFieldGate
{
    /**
     * Determine whether the viewer can see owner-only fields
     * (original URL, processing_error, etc.).
     */
    public function canViewSensitiveFields(?User $viewer, Photo $photo): bool
    {
        return $viewer !== null && $viewer->can('view', $photo);
    }
}
