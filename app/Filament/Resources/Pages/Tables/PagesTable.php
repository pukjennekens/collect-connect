<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                \Filament\Tables\Columns\IconColumn::make('is_published')->boolean(),
                TextColumn::make('published_at')->dateTime()->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('slug')->searchable(),
            ])
            ->filters([\Filament\Tables\Filters\TernaryFilter::make('is_published')])
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
