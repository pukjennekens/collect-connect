<?php

declare(strict_types=1);

namespace App\Filament\Resources\SyncRuns\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SyncRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([TextColumn::make('source')->searchable()->sortable()->wrap(), TextColumn::make('status')->searchable()->sortable()->wrap(), TextColumn::make('error')->searchable()->sortable()->wrap(), TextColumn::make('started_at')->searchable()->sortable()->wrap(), TextColumn::make('finished_at')->searchable()->sortable()->wrap()]);
    }
}
