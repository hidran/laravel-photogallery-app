<?php

declare(strict_types=1);

namespace App\Filament\Resources\Albums\Schemas;

use App\Models\Photo;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

final class AlbumForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('user_id', auth()->id()),
                    ),
                Textarea::make('description')
                    ->maxLength(1000)
                    ->rows(3),
                Select::make('cover_photo_id')
                    ->label('Cover Photo')
                    ->relationship('coverPhoto', 'title')
                    ->options(function ($record) {
                        if (! $record) {
                            return [];
                        }

                        return Photo::query()
                            ->where('album_id', $record->id)
                            ->pluck('title', 'id');
                    })
                    ->searchable()
                    ->nullable(),
            ]);
    }
}
