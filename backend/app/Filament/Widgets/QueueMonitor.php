<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ProcessingStatus;
use App\Models\Photo;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

final class QueueMonitor extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '5s';

    protected function getStats(): array
    {
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $photosProcessing = Photo::query()
            ->where('processing_status', ProcessingStatus::Processing)
            ->count();

        return [
            Stat::make('Pending Jobs', $pendingJobs)
                ->color($pendingJobs > 0 ? 'warning' : 'success'),
            Stat::make('Failed Jobs', $failedJobs)
                ->color($failedJobs > 0 ? 'danger' : 'success'),
            Stat::make('Photos Processing', $photosProcessing)
                ->color($photosProcessing > 0 ? 'info' : 'success'),
        ];
    }
}
