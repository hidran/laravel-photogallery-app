<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Photo;
use Illuminate\Foundation\Events\Dispatchable;

final class PhotoUploaded
{
    use Dispatchable;

    public function __construct(public readonly Photo $photo) {}
}
