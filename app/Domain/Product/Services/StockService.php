<?php

declare(strict_types=1);

namespace App\Domain\Product\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class StockService
{
    public const string INTEGRATION_LOCK = 'bricqer:stock-and-orders';

    public function heldQuantity(Product $product): int
    {
        return (int) $this->heldItems($product)->sum('quantity');
    }

    /** The caller holds the product row lock inside an order transaction.
     * @return list<array{definition_id: int, quantity: int}>
     */
    public function reserve(Product $product, int $quantity): array
    {
        if ($quantity < 1 || ! $product->isPurchasable() || $quantity > $product->stock) {
            throw ValidationException::withMessages(['cart' => 'Voorraad onvoldoende voor '.$product->productable?->name.'.']);
        }

        $definitions = $this->definitions($product->source_definitions);
        $allocated = [];
        $remaining = $quantity;
        $held = [];
        $this->heldItems($product)->each(function (OrderItem $item) use (&$held): void {
            foreach ($this->definitions($item->getAttribute('source_allocations')) as $allocation) {
                $id = $allocation['definition_id'];
                $held[$id] = ($held[$id] ?? 0) + $allocation['quantity'];
            }
        });
        foreach ($definitions as $definition) {
            $id = (int) $definition['definition_id'];
            $available = max(0, (int) $definition['quantity'] - ($held[$id] ?? 0));
            $take = min($remaining, $available);
            if ($id > 0 && $take > 0) {
                $allocated[] = ['definition_id' => $id, 'quantity' => $take];
                $remaining -= $take;
            }
        }
        if ($remaining > 0 && (config('bricqer.orders_enabled') || $definitions !== [])) {
            throw ValidationException::withMessages(['cart' => 'De bronvoorraad is gewijzigd. Probeer opnieuw na de voorraadupdate.']);
        }
        $product->forceFill([
            'bricqer_stock' => $product->bricqer_stock ?? ($product->stock + $this->heldQuantity($product)),
            'stock' => $product->stock - $quantity,
        ])->save();

        return $allocated;
    }

    /** Release only the local hold. Bricqer stock is never incremented. */
    public function release(Order $order): void
    {
        $legacy = ! $order->stock_held && $order->stock_reserved_at !== null;
        $order->forceFill(['stock_held' => false, 'stock_reserved_at' => null])->save();
        foreach ($order->items()->orderBy('product_id')->get() as $item) {
            $product = Product::query()->lockForUpdate()->find($item->product_id);
            if ($product === null) {
                continue;
            }
            $product->forceFill(['stock' => $product->bricqer_stock === null && $legacy
                ? $product->stock + $item->quantity
                : max(0, (int) $product->bricqer_stock - $this->heldQuantity($product))])->save();
        }
    }

    /**
     * Use a locking join rather than a snapshot SUM or whereHas subquery.
     * Under MySQL REPEATABLE READ, a transaction may have read metadata before
     * waiting for the product lock. Both order state and items must be current
     * reads so a newly committed reservation is still deducted.
     *
     * @return Collection<int, OrderItem>
     */
    private function heldItems(Product $product): Collection
    {
        $items = (new OrderItem)->getTable();
        $orders = (new Order)->getTable();

        return OrderItem::query()->select($items.'.*')
            ->join($orders, $orders.'.id', '=', $items.'.order_id')
            ->where($items.'.product_id', $product->id)
            ->where(function (Builder $query) use ($orders): void {
                $query->where($orders.'.stock_held', true)
                    ->orWhere(function (Builder $query) use ($orders): void {
                        $query->where($orders.'.status', 'pending_payment')
                            ->whereNotNull($orders.'.stock_reserved_at');
                    });
            })
            ->orderBy($items.'.order_id')->orderBy($items.'.id')
            ->lockForUpdate()->get();
    }

    /** @return list<array{definition_id: int, quantity: int}> */
    private function definitions(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (! is_array($value)) {
            throw ValidationException::withMessages(['cart' => 'Ongeldige bronvoorraad. Wacht op een voorraadupdate.']);
        }
        $definitions = [];
        foreach ($value as $definition) {
            if (! is_array($definition)) {
                throw ValidationException::withMessages(['cart' => 'Ongeldige bronvoorraad. Wacht op een voorraadupdate.']);
            }
            $id = filter_var($definition['definition_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($definition['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1 || $quantity === false || $quantity < 0) {
                throw ValidationException::withMessages(['cart' => 'Ongeldige bronvoorraad. Wacht op een voorraadupdate.']);
            }
            $definitions[] = ['definition_id' => $id, 'quantity' => $quantity];
        }

        return $definitions;
    }
}
