<?php

declare(strict_types=1);

namespace App\Domain\Bricqer\Jobs;

use App\Mail\StockBackInStockMail;
use App\Models\StockNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class DispatchStockNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  list<int>  $productIds
     */
    public function __construct(public array $productIds) {}

    public int $tries = 5;

    public int $timeout = 60;

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function handle(): void
    {
        if ($this->productIds === []) {
            return;
        }

        StockNotification::query()
            ->whereIn('product_id', $this->productIds)
            ->whereNull('notified_at')
            ->with('product.productable')
            ->each(function (StockNotification $notification): void {
                Cache::lock('stock-notification:'.$notification->id, 120)->block(5, function () use ($notification): void {
                    $notification->refresh()->load('product.productable');
                    if ($notification->notified_at || ! $notification->product || ! $notification->product->isPurchasable()) {
                        return;
                    }
                    Mail::to($notification->email)->send(new StockBackInStockMail($notification->product));
                    $notification->update(['notified_at' => now()]);
                });
            });
    }
}
