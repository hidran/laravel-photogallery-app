<?php

declare(strict_types=1);

namespace App\Enums;

enum TokenAbility: string
{
    case PhotosWrite = 'photos:write';
    case AlbumsWrite = 'albums:write';
    case Admin = 'admin';
}
