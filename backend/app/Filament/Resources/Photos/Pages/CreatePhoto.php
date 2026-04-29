<?php

declare(strict_types=1);

namespace App\Filament\Resources\Photos\Pages;

use App\Enums\ProcessingStatus;
use App\Filament\Resources\Photos\PhotoResource;
use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

final class CreatePhoto extends CreateRecord
{
    protected static string $resource = PhotoResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $path = (string) $data['original_path'];
        $disk = Storage::disk('photos_private');

        $data['user_id'] = auth()->id();
        $data['filename'] = basename($path);
        $data['file_size'] = $disk->exists($path) ? $disk->size($path) : 0;
        $data['mime_type'] = $disk->exists($path) ? ($disk->mimeType($path) ?: 'image/jpeg') : 'image/jpeg';
        $data['processing_status'] = ProcessingStatus::Pending;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Photo $photo */
        $photo = $this->record;
        ProcessPhoto::dispatch($photo);
    }
}
