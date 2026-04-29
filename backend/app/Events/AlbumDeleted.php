<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Album;
use Illuminate\Foundation\Events\Dispatchable;

final class AlbumDeleted
{
    use Dispatchable;

    public function __construct(public readonly Album $album) {}
}
