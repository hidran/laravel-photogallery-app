<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

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
                    ->height(40),
                TextColumn::make('title')
                    ->limit(30),
                TextColumn::make('user.name')
                    ->label('User'),
                TextColumn::make('album.name')
                    ->label('Album')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->since(),
            ])
            ->paginated(false)
            ->poll('10s');
    }
}
