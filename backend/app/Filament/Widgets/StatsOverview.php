<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Tag;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalStorage = Photo::query()->sum('file_size');

        return [
            Stat::make('Total Photos', Photo::query()->count()),
            Stat::make('Albums', Album::query()->count()),
            Stat::make('Tags', Tag::query()->count()),
            Stat::make('Storage', $this->formatBytes((int) $totalStorage)),
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
