<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockNotifications\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([TextColumn::make('product_id')->searchable()->sortable()->wrap(), TextColumn::make('email')->searchable()->sortable()->wrap(), TextColumn::make('notified_at')->searchable()->sortable()->wrap(), TextColumn::make('created_at')->searchable()->sortable()->wrap()]);
    }
}
