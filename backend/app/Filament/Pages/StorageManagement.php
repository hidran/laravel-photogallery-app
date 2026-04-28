<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

final class StorageManagement extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.storage-management';

    public function getTitle(): string
    {
        return 'Storage Management';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerateThumbnails')
                ->label('Regenerate All Thumbnails')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    Photo::query()->chunk(100, function ($photos): void {
                        foreach ($photos as $photo) {
                            ProcessPhoto::dispatch($photo);
                        }
                    });

                    Notification::make()
                        ->title('Thumbnail regeneration dispatched')
                        ->success()
                        ->send();
                }),
            Action::make('purgeFailedJobs')
                ->label('Purge Failed Jobs')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This will permanently delete all failed jobs. This action cannot be undone.')
                ->action(function (): void {
                    DB::table('failed_jobs')->truncate();

                    Notification::make()
                        ->title('Failed jobs purged')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getFolderBreakdown(): array
    {
        $disk = Storage::disk('photos');
        $privateDisk = Storage::disk('photos_private');

        return [
            'originals' => $this->getFolderStats($privateDisk, 'originals'),
            'thumbnails' => $this->getFolderStats($disk, 'thumbnails'),
            'medium' => $this->getFolderStats($disk, 'medium'),
            'large' => $this->getFolderStats($disk, 'large'),
        ];
    }

    /**
     * @return array{size: string, count: int}
     */
    private function getFolderStats(mixed $disk, string $folder): array
    {
        $files = $disk->files($folder);
        $totalSize = 0;

        foreach ($files as $file) {
            $totalSize += $disk->size($file);
        }

        return [
            'size' => $this->formatBytes($totalSize),
            'count' => count($files),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 1).' '.$units[$i];
    }
}
