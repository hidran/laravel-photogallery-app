<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ProcessingStatus;
use App\Models\Photo;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class QueueMonitor extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '5s';

    protected function getStats(): array
    {
        $stats = Cache::remember('queue_monitor_stats', 10, function () {
            return [
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
                'processing' => Photo::where('processing_status', ProcessingStatus::Processing)->count(),
            ];
        });

        return [
            Stat::make('Pending Jobs', $stats['pending'])
                ->color($stats['pending'] > 0 ? 'warning' : 'success'),
            Stat::make('Failed Jobs', $stats['failed'])
                ->color($stats['failed'] > 0 ? 'danger' : 'success'),
            Stat::make('Photos Processing', $stats['processing'])
                ->color($stats['processing'] > 0 ? 'info' : 'success'),
        ];
    }
}
