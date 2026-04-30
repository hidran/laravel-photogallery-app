<?php

declare(strict_types=1);

namespace App\Filament\Resources\Photos\Tables;

use App\Enums\ProcessingStatus;
use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use App\Support\HumanBytes;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class PhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail_path')
                    ->label('Thumbnail')
                    ->disk('photos')
                    ->width(60)
                    ->height(60)
                    ->grow(false),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('album.name')
                    ->label('Album')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn (int $state): string => HumanBytes::format($state))
                    ->sortable()
                    ->grow(false)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('favorited_by_count')
                    ->counts('favoritedBy')
                    ->label('Fav')
                    ->sortable()
                    ->grow(false)
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->grow(false)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('album')
                    ->relationship('album', 'name'),

                SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple(),

                Filter::make('has_favorites')
                    ->query(fn (Builder $query): Builder => $query->has('favoritedBy'))
                    ->label('Has favorites'),

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
                Action::make('reprocess')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Photo $record): void {
                        $record->update([
                            'processing_status' => ProcessingStatus::Pending,
                            'processing_attempts' => 0,
                            'processing_error' => null,
                        ]);
                        ProcessPhoto::dispatch($record);
                        Notification::make()->title('Reprocessing dispatched')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('assignToAlbum')
                        ->label('Assign to Album')
                        ->icon('heroicon-o-folder')
                        ->form([
                            Select::make('album_id')
                                ->label('Album')
                                ->relationship('album', 'name')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['album_id' => $data['album_id']]);
                            Notification::make()->title('Photos assigned to album')->success()->send();
                        }),
                    BulkAction::make('reprocess')
                        ->label('Reprocess')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(function (Photo $photo): void {
                                $photo->update([
                                    'processing_status' => ProcessingStatus::Pending,
                                    'processing_attempts' => 0,
                                    'processing_error' => null,
                                ]);
                                ProcessPhoto::dispatch($photo);
                            });
                            Notification::make()->title('Reprocessing dispatched')->success()->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
