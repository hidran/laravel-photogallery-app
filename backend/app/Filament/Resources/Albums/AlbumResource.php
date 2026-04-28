<?php

declare(strict_types=1);

namespace App\Filament\Resources\Albums;

use App\Filament\Resources\Albums\Pages\CreateAlbum;
use App\Filament\Resources\Albums\Pages\EditAlbum;
use App\Filament\Resources\Albums\Pages\ListAlbums;
use App\Filament\Resources\Albums\RelationManagers\PhotosRelationManager;
use App\Filament\Resources\Albums\Schemas\AlbumForm;
use App\Filament\Resources\Albums\Tables\AlbumsTable;
use App\Models\Album;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class AlbumResource extends Resource
{
    protected static ?string $model = Album::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Gallery';

    public static function form(Schema $schema): Schema
    {
        return AlbumForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AlbumsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlbums::route('/'),
            'create' => CreateAlbum::route('/create'),
            'edit' => EditAlbum::route('/{record}/edit'),
        ];
    }
}
