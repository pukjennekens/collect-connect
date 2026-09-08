<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Bricqer\Jobs\DispatchStockNotificationsJob;
use App\Models\StockNotification;
use Illuminate\Console\Command;

class NotifyRestockedProducts extends Command
{
    protected $signature = 'stock:notify-restocked';

    protected $description = 'Retry pending subscriptions for products currently available';

    public function handle(): int
    {
        StockNotification::query()->whereNull('notified_at')
            ->whereHas('product', fn ($query) => $query->where('is_active', true)->where('stock', '>', 0))
            ->select('id', 'product_id')->chunkById(500, function ($subscriptions): void {
                DispatchStockNotificationsJob::dispatch(array_values(array_unique($subscriptions->map(fn (StockNotification $subscription): int => (int) $subscription->product_id)->all())));
            });

        return self::SUCCESS;
    }
}
