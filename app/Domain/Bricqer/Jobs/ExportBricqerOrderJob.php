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
use RuntimeException;
use Throwable;

class ExportBricqerOrderJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $orderId) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(3);
    }

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function handle(BricqerOrderService $service): void
    {
        if (! config('bricqer.orders_enabled')) {
            return;
        }
        Cache::lock(StockService::INTEGRATION_LOCK, 1900)->block(10, function () use ($service): void {
            $order = Order::query()->findOrFail($this->orderId);
            try {
                $service->export($order);
            } catch (Throwable $exception) {
                if (! in_array($order->sync_status, ['submitting', 'ambiguous', 'exported', 'synced'], true)) {
                    $order->forceFill(['sync_status' => 'failed', 'sync_error' => mb_substr($exception->getMessage(), 0, 1000)])->save();
                }
                throw $exception;
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        report($exception ?? new RuntimeException('Bricqer order export failed.'));
    }
}
