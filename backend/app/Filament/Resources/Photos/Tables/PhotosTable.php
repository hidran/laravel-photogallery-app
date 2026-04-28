<?php

declare(strict_types=1);

namespace App\Filament\Resources\Photos\Tables;

use App\Enums\ProcessingStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail_path')
                    ->label('Thumbnail')
                    ->disk('photos')
                    ->width(60)
                    ->height(60),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('album.name')
                    ->label('Album')
                    ->sortable(),

                TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge(),

                TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn (int $state): string => self::humanBytes($state))
                    ->sortable(),

                ToggleColumn::make('is_favorite')
                    ->label('Favorite'),

                TextColumn::make('processing_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (ProcessingStatus $state): string => match ($state) {
                        ProcessingStatus::Pending => 'gray',
                        ProcessingStatus::Processing => 'info',
                        ProcessingStatus::Completed => 'success',
                        ProcessingStatus::Failed => 'danger',
                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('album')
                    ->relationship('album', 'name'),

                SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple(),

                TernaryFilter::make('is_favorite')
                    ->label('Favorite'),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->label('Date range'),

                SelectFilter::make('processing_status')
                    ->options(ProcessingStatus::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.1f %s', $bytes / (1024 ** $factor), $units[(int) $factor]);
    }
}
