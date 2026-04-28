<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($set, ?string $state): void {
                        $set('slug', Str::slug($state ?? ''));
                    }),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->prefixIcon('heroicon-o-lock-closed')
                    ->readOnlyOn('edit'),
            ]);
    }
}
