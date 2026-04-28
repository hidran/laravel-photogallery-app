<?php

declare(strict_types=1);

namespace App\Filament\Resources\Albums\Pages;

use App\Filament\Resources\Albums\AlbumResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAlbum extends CreateRecord
{
    protected static string $resource = AlbumResource::class;
}
