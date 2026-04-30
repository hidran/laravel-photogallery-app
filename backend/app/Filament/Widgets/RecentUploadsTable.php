<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ProcessingStatus;
use App\Models\Photo;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class RecentUploadsTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Photo::query()
                    ->with(['user:id,name', 'album:id,name'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                ImageColumn::make('thumbnail_path')
                    ->label('Thumb')
                    ->disk('photos')
                    ->width(40)
                    ->height(40)
                    ->grow(false),
                TextColumn::make('title')
                    ->limit(30),
                TextColumn::make('user.name')
                    ->label('User')
                    ->grow(false),
                TextColumn::make('album.name')
                    ->label('Album')
                    ->placeholder('—')
                    ->grow(false),
                TextColumn::make('processing_status')
                    ->label('Status')
                    ->badge()
                    ->grow(false)
                    ->color(fn (ProcessingStatus $state): string => match ($state) {
                        ProcessingStatus::Pending => 'gray',
                        ProcessingStatus::Processing => 'info',
                        ProcessingStatus::Completed => 'success',
                        ProcessingStatus::Failed => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->since()
                    ->grow(false),
            ])
            ->paginated(false)
            ->poll('10s');
    }
}
