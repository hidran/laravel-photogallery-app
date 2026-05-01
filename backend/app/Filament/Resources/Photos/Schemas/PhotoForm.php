<?php

declare(strict_types=1);

namespace App\Filament\Resources\Photos\Schemas;

use App\Models\Album;
use App\Models\Tag;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class PhotoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('original_path')
                    ->label('Photo')
                    ->disk('photos_private')
                    ->directory('originals')
                    ->image()
                    ->imagePreviewHeight('200')
                    ->maxSize(10240)
                    ->required()
                    ->visibleOn('create'),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(3)
                    ->maxLength(1000),
                Select::make('album_id')
                    ->label('Album')
                    ->relationship('album', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description'),
                    ])
                    ->createOptionUsing(function (array $data): string {
                        $album = Album::create([
                            'user_id' => auth()->id(),
                            ...$data,
                        ]);

                        return $album->id;
                    }),
                Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(function (array $data): string {
                        $slug = Str::slug($data['name']);
                        $tag = Tag::firstOrCreate(
                            ['slug' => $slug],
                            ['name' => $data['name']],
                        );

                        return $tag->id;
                    }),
            ]);
    }
}
