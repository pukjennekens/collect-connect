<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ArticleForm
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
            RichEditor::make('content')->required()->columnSpanFull(),
        ]);
    }
}
