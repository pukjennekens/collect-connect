<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('number'), TextEntry::make('email'), TextEntry::make('total_cents'), TextEntry::make('payment_status'), TextEntry::make('fulfilment_status'), TextEntry::make('sync_status'), TextEntry::make('created_at'), TextEntry::make('name'), TextEntry::make('shipping_line1'), TextEntry::make('shipping_postal_code'), TextEntry::make('shipping_city'), TextEntry::make('shipping_country_code'), TextEntry::make('tracking_code'), TextEntry::make('sync_error')]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([TextColumn::make('number')->searchable()->sortable(), TextColumn::make('email')->searchable()->sortable(), TextColumn::make('total_cents')->searchable()->sortable(), TextColumn::make('payment_status')->searchable()->sortable(), TextColumn::make('fulfilment_status')->searchable()->sortable(), TextColumn::make('sync_status')->searchable()->sortable(), TextColumn::make('created_at')->searchable()->sortable()])->recordActions([ViewAction::make(),
            Action::make('retry')->label('Retry failed export')->requiresConfirmation()
                ->visible(fn (Order $record): bool => $record->sync_status === 'failed' && $record->paid_at !== null)
                ->action(fn (Order $record) => \App\Domain\Bricqer\Jobs\ExportBricqerOrderJob::dispatch($record->id)),
            Action::make('reconcile')->label('Refresh Bricqer status')->requiresConfirmation()
                ->visible(fn (Order $record): bool => $record->bricqer_order_id !== null || in_array($record->sync_status, ['ambiguous', 'submitting'], true))
                ->action(fn (Order $record) => \App\Domain\Bricqer\Jobs\SyncBricqerOrdersJob::dispatch($record->id)), ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOrders::route('/')];
    }
}
