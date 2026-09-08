<?php

declare(strict_types=1);

namespace App\Domain\Bricqer\Jobs;

use App\Domain\Bricqer\Services\BricqerOrderService;
use App\Domain\Product\Services\StockService;
use App\Models\Order;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncBricqerOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public ?int $orderId = null) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(3);
    }

    public function uniqueId(): string
    {
        return (string) ($this->orderId ?? 'all');
    }

    public function handle(BricqerOrderService $service): void
    {
        if (! config('bricqer.orders_enabled')) {
            return;
        }
        if ($this->orderId === null) {
            Order::query()->whereNotNull('paid_at')->whereNull('bricqer_order_id')->where('sync_status', 'pending')->select('id')->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    ExportBricqerOrderJob::dispatch($order->id);
                }
            });
            Order::query()->where(fn ($query) => $query->whereNotNull('bricqer_order_id')->orWhereIn('sync_status', ['ambiguous', 'submitting']))
                ->select('id')->chunkById(100, function ($orders): void {
                    foreach ($orders as $order) {
                        self::dispatch($order->id);
                    }
                });

            return;
        }
        Cache::lock(StockService::INTEGRATION_LOCK, 1900)->block(10, fn () => $service->sync(Order::query()->findOrFail($this->orderId)));
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->orderId) {
            Order::query()->whereKey($this->orderId)->update(['sync_error' => mb_substr($exception?->getMessage() ?? 'Synchronization failed.', 0, 1000)]);
        }
    }
}
