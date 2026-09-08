<?php

declare(strict_types=1);

namespace App\Domain\Order\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class ReleaseUnpaidOrderStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @return array{released: int}
     */
    public function handle(): array
    {
        $ttl = max(1, (int) config('orders.reservation_ttl_minutes', 60));
        $cutoff = now()->subMinutes($ttl);
        $released = 0;

        Order::query()
            ->where('status', 'pending_payment')
            ->whereNotNull('stock_reserved_at')
            ->where('stock_reserved_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(50, function ($orders) use (&$released): void {
                foreach ($orders as $order) {
                    if ($this->releaseOrder($order)) {
                        $released++;
                    }
                }
            });

        return ['released' => $released];
    }

    /**
     * Restock and cancel a single order, if it is still an unpaid reservation.
     * Locks the row itself so a concurrent payment callback cannot race this
     * release; returns false without changes if that race is lost.
     */
    private function releaseOrder(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            /** @var Order|null $locked */
            $locked = Order::query()->lockForUpdate()->find($order->id);

            if (
                $locked === null
                || $locked->status !== 'pending_payment'
                || $locked->stock_reserved_at === null
            ) {
                return false;
            }

            $locked->load('items');

            app(\App\Domain\Product\Services\StockService::class)->release($locked);

            $meta = $locked->meta ?? [];
            $meta['stock_released_at'] = now()->toIso8601String();
            $meta['stock_release_reason'] = 'unpaid_reservation_expired';

            $locked->forceFill([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'stock_reserved_at' => null,
                'meta' => $meta,
            ])->save();

            return true;
        }, 3);
    }
}
