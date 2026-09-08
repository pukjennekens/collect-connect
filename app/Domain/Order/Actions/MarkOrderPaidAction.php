<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Payment\PaymentResult;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Settles a pending order once a gateway confirms payment. Written to be the
 * single entry point for any driver's callback/webhook, not just the local
 * payment simulator.
 */
class MarkOrderPaidAction
{
    public function handle(Order $order, PaymentResult $result): Order
    {
        return DB::transaction(function () use ($order, $result): Order {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            // Already settled: callbacks may arrive more than once.
            if ($locked->paid_at !== null) {
                return $locked;
            }

            if (! $result->isPaid() || $locked->status !== 'pending_payment' || $locked->stock_reserved_at === null || $locked->stock_reserved_at->lte(now()->subMinutes((int) config('orders.reservation_ttl_minutes', 60)))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'Deze bestelling kan niet meer worden betaald. Maak een nieuwe bestelling.']);
            }
            $meta = $locked->meta ?? [];
            $meta['payment_confirmed_at'] = now()->toIso8601String();
            $meta['payment_payload'] = $result->payload;
            unset($meta['note']);

            $locked->forceFill([
                'status' => 'paid',
                'payment_status' => 'paid',
                'sync_status' => config('bricqer.orders_enabled') ? 'pending' : 'disabled',
                'paid_at' => now(),
                // Stock is committed now, so it is no longer a reservation that
                // ReleaseUnpaidOrderStockJob may hand back.
                'stock_reserved_at' => null,
                'payment_provider' => $result->provider,
                'payment_reference' => $result->reference ?? $locked->payment_reference,
                'meta' => $meta,
            ])->save();

            if (config('bricqer.orders_enabled')) {
                \App\Domain\Bricqer\Jobs\ExportBricqerOrderJob::dispatch($locked->id)->afterCommit();
            }
            \Illuminate\Support\Facades\Mail::to($locked->email)->queue((new \App\Mail\OrderPaidMail($locked))->afterCommit());

            return $locked;
        });
    }
}
