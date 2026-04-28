<?php

declare(strict_types=1);

namespace App\Filament\Resources\Albums\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AlbumsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('coverPhoto.thumbnail_path')
                    ->label('Cover')
                    ->disk('photos')
                    ->square()
                    ->size(40),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('photos_count')
                    ->counts('photos')
                    ->label('Photos')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
