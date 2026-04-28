<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Tag;
use App\Support\HumanBytes;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalStorage = Photo::query()->sum('file_size');

        return [
            Stat::make('Total Photos', Photo::query()->count()),
            Stat::make('Albums', Album::query()->count()),
            Stat::make('Tags', Tag::query()->count()),
            Stat::make('Storage', HumanBytes::format((int) $totalStorage)),
        ];
    }
}
