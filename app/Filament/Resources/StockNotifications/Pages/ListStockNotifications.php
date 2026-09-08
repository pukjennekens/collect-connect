<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockNotifications\Pages;

use App\Filament\Resources\StockNotifications\StockNotificationResource;
use Filament\Resources\Pages\ListRecords;

class ListStockNotifications extends ListRecords
{
    protected static string $resource = StockNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
