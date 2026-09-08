<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true),
            Toggle::make('is_published')->default(false),
            DateTimePicker::make('published_at')->helperText('Leave empty to publish immediately when enabled.'),
            TextInput::make('meta_title')->maxLength(255),
            Textarea::make('meta_description')->maxLength(500),
            Builder::make('blocks')->columnSpanFull()->blocks([
                Block::make('legacy')->label('Existing layout')->schema([
                    Placeholder::make('notice')->content('Existing layout is preserved. Add new content blocks below, or remove this block to replace it.'),
                    Hidden::make('row'),
                ]),
                Block::make('heading')->schema([TextInput::make('text')->required()]),
                Block::make('text')->schema([Textarea::make('text')->required()]),
                Block::make('html')->label('Rich text')->schema([RichEditor::make('html')->required()]),
                Block::make('image')->schema([
                    TextInput::make('src')->url()->required()->helperText('Public HTTPS image URL'),
                    TextInput::make('alt')->required(),
                ]),
            ]),
        ]);
    }
}
