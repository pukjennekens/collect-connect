<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Minifig;
use App\Models\Part;
use App\Models\Pivots\PartColor;
use App\Models\Product;
use App\Models\User;
use App\Support\MediaUrl;
use Binafy\LaravelCart\Models\Cart;
use Closure;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartService
{
    private const SESSION_KEY_PREFIX = 'cart_';

    protected function userId(): string
    {
        if (auth()->check()) {
            return (string) auth()->id();
        }
        if (! session()->has('cart_guest_id')) {
            session()->put('cart_guest_id', (string) Str::uuid());
        }

        return (string) session('cart_guest_id');
    }

    protected function sessionKey(): string
    {
        return self::SESSION_KEY_PREFIX.$this->userId();
    }

    /** @return array<int, array{itemable_id: int, itemable_type: string, quantity: int}> */
    public function getRawItems(): array
    {
        if (! auth()->check()) {
            return session($this->sessionKey(), []);
        }

        if (session()->has($this->sessionKey())) {
            $this->mergeGuestCartIntoUser(auth()->id() ?? abort(401));
        }

        return $this->databaseItems((string) auth()->id());
    }

    /** @return array<int, array{itemable_id: int, itemable_type: string, quantity: int}> */
    private function databaseItems(string $userId): array
    {
        $cart = Cart::query()->without('items')->where('user_id', $userId)->first();

        return $cart?->items()->where('itemable_type', Product::class)->get()
            ->map(fn ($item): array => [
                'itemable_id' => (int) $item->getAttribute('itemable_id'),
                'itemable_type' => Product::class,
                'quantity' => (int) $item->getAttribute('quantity'),
            ])->all() ?? [];
    }

    /** @param array<int, array{itemable_id: int, itemable_type: string, quantity: int}> $items */
    private function storeDatabaseItems(string $userId, array $items): void
    {
        $cart = Cart::query()->without('items')->firstOrCreate(['user_id' => $userId]);
        $ids = array_column($items, 'itemable_id');
        $cart->items()->whereNotIn('itemable_id', $ids)->delete();
        $existing = $cart->items()->get()->keyBy('itemable_id');
        foreach ($items as $item) {
            $line = $existing->get($item['itemable_id']);
            if ($line === null) {
                $cart->items()->create($item);
            } elseif ((int) $line->getAttribute('quantity') !== $item['quantity']) {
                $line->update(['quantity' => $item['quantity']]);
            }
        }
    }

    /**
     * Serialize all account cart changes, including first creation, with the user row.
     * Guest requests use Laravel route session blocking.
     *
     * @param  Closure(array<int, array{itemable_id: int, itemable_type: string, quantity: int}>): array<int, array{itemable_id: int, itemable_type: string, quantity: int}>  $change
     */
    private function mutate(Closure $change): void
    {
        if (! auth()->check()) {
            session()->put($this->sessionKey(), $change($this->getRawItems()));

            return;
        }

        if (session()->has($this->sessionKey())) {
            $this->mergeGuestCartIntoUser(auth()->id() ?? abort(401));
        }

        DB::transaction(function () use ($change): void {
            $userId = (string) auth()->id();
            User::query()->lockForUpdate()->findOrFail($userId);
            $items = $this->databaseItems($userId);
            $next = $change($items);
            if ($next !== $items) {
                $this->storeDatabaseItems($userId, $next);
            }
        }, 3);
    }

    /** @return list<string> */
    public function revalidateStock(): array
    {
        $messages = [];
        $this->mutate(function (array $items) use (&$messages): array {
            $products = Product::query()->with('productable')->whereIn('id', array_column($items, 'itemable_id'))->get()->keyBy('id');
            $next = [];
            foreach ($items as $item) {
                $product = $products->get($item['itemable_id']);
                if ($product === null || ! $product->isPurchasable()) {
                    $messages[] = 'Een artikel is niet meer beschikbaar en is uit je winkelwagen gehaald.';

                    continue;
                }
                if ($item['quantity'] > $product->stock) {
                    $item['quantity'] = $product->stock;
                    $messages[] = "Voorraad bijgewerkt voor product #{$product->id}.";
                }
                $next[] = $item;
            }

            return $next;
        });

        return array_values(array_unique($messages));
    }

    public function addItem(Product $product, int $quantity): void
    {
        $this->changeQuantity($product, $quantity, true);
    }

    public function setQuantity(Product $product, int $quantity): void
    {
        $this->changeQuantity($product, $quantity, false);
    }

    private function changeQuantity(Product $product, int $quantity, bool $add): void
    {
        $this->mutate(function (array $items) use ($product, $quantity, $add): array {
            $product = $product->fresh();
            if ($product === null) {
                return $items;
            }
            $byId = collect($items)->keyBy('itemable_id');
            $existing = $byId->get($product->id);
            if (! $add && $existing === null) {
                return $items;
            }
            $requested = $add ? ($existing['quantity'] ?? 0) + max(0, $quantity) : $quantity;
            $capped = $product->isPurchasable() ? max(0, min($requested, $product->stock)) : 0;
            if ($capped === 0) {
                $byId->forget($product->id);
            } else {
                $byId->put($product->id, ['itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => $capped]);
            }

            return $byId->values()->all();
        });
    }

    public function removeItem(Product $product): void
    {
        $this->mutate(fn (array $items): array => array_values(array_filter(
            $items, fn (array $item): bool => (int) $item['itemable_id'] !== $product->id,
        )));
    }

    public function clear(): void
    {
        $this->mutate(fn (array $items): array => []);
    }

    public function mergeGuestCartIntoUser(int|string $userId): void
    {
        $guestId = session('cart_guest_id');
        $guestKey = self::SESSION_KEY_PREFIX.(string) $guestId;
        $guestItems = session($guestKey, []);
        $legacyKey = self::SESSION_KEY_PREFIX.(string) $userId;
        $legacyItems = session($legacyKey, []);
        if ($guestItems === [] && $legacyItems === []) {
            return;
        }

        DB::transaction(function () use ($userId, $guestId, $guestItems, $legacyItems): void {
            User::query()->lockForUpdate()->findOrFail($userId);
            if ($guestId !== null && DB::table('cart_guest_merges')->where('guest_id', $guestId)->exists()) {
                return;
            }
            $items = collect($this->databaseItems((string) $userId))->keyBy('itemable_id');
            foreach ([...$legacyItems, ...$guestItems] as $item) {
                $id = (int) ($item['itemable_id'] ?? 0);
                $existing = $items->get($id);
                $items->put($id, ['itemable_id' => $id, 'itemable_type' => Product::class,
                    'quantity' => ($existing['quantity'] ?? 0) + (int) ($item['quantity'] ?? 0)]);
            }
            $products = Product::query()->with('productable')->whereIn('id', $items->keys())->get()->keyBy('id');
            $merged = $items->map(function (array $item) use ($products): array {
                $product = $products->get($item['itemable_id']);
                $item['quantity'] = $product?->isPurchasable() ? max(0, min($item['quantity'], $product->stock)) : 0;

                return $item;
            })->filter(fn (array $item): bool => $item['quantity'] > 0)->values()->all();
            $this->storeDatabaseItems((string) $userId, $merged);
            if ($guestId !== null) {
                DB::table('cart_guest_merges')->insert(['guest_id' => $guestId, 'user_id' => $userId, 'created_at' => now()]);
            }
        }, 3);
        session()->forget([$guestKey, $legacyKey, 'cart_guest_id']);
    }

    /**
     * @return Collection<int, array{id: int, name: string, lego_number: string|null, image: string|null, price: float, quantity: int, stock: int, color: string|null, color_hex: string|null, weight_grams: float|null}>
     */
    public function getItems(): Collection
    {
        $this->revalidateStock();
        $rawItems = $this->getRawItems();

        if ($rawItems === []) {
            return collect();
        }

        $productIds = array_column($rawItems, 'itemable_id');
        $products = Product::whereIn('id', $productIds)
            ->with([
                'color',
                'productable' => function ($relation): void {
                    if ($relation instanceof MorphTo) {
                        $relation->morphWith([Part::class => ['partColors.media'], Minifig::class => ['media']]);
                    }
                },
            ])
            ->get()
            ->keyBy('id');

        return collect($rawItems)
            ->map(function (array $item) use ($products): ?array {
                /** @var Product|null $product */
                $product = $products[$item['itemable_id']] ?? null;

                if ($product === null || $product->productable === null) {
                    return null;
                }

                return [
                    'id' => $product->id,
                    'name' => $product->productable->name,
                    'lego_number' => $product->productable->bricklink_id ?? null,
                    'image' => $this->imageFor($product),
                    'price' => $product->getPrice(),
                    'quantity' => $item['quantity'],
                    'stock' => $product->stock,
                    'color' => $product->color?->name,
                    'color_hex' => $product->color?->hex,
                    'weight_grams' => $this->unitWeightGrams($product),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Total cart weight in grams (sum of unit weight × quantity).
     * Lines without a known weight are excluded from the sum.
     */
    public function getTotalWeightGrams(): float
    {
        return (float) $this->getItems()->sum(
            fn (array $item): float => ($item['weight_grams'] ?? 0) * $item['quantity'],
        );
    }

    protected function unitWeightGrams(Product $product): ?float
    {
        $weight = data_get($product->productable, 'weight_grams');

        if ($weight === null) {
            return null;
        }

        return (float) $weight;
    }

    public function getTotal(): int
    {
        $rawItems = $this->getRawItems();

        if ($rawItems === []) {
            return 0;
        }

        $productIds = array_column($rawItems, 'itemable_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        return (int) collect($rawItems)->sum(function (array $item) use ($products): int {
            $product = $products[$item['itemable_id']] ?? null;

            return $product !== null ? (int) $product->price * $item['quantity'] : 0;
        });
    }

    /**
     * Cart thumbnails are media-library only (no Bricqer CDN hotlinking).
     */
    protected function imageFor(Product $product): ?string
    {
        if ($product->productable instanceof Part) {
            $partColor = $product->productable->partColors->firstWhere('color_id', $product->color_id);

            return MediaUrl::fromMedia(
                $partColor?->getFirstMedia(PartColor::BRICQER_IMAGE_COLLECTION),
                [PartColor::THUMB_CONVERSION, PartColor::MEDIUM_CONVERSION, PartColor::LARGE_CONVERSION],
            );
        }

        if ($product->productable instanceof Minifig) {
            return MediaUrl::fromMedia(
                $product->productable->getFirstMedia(Minifig::BRICQER_IMAGE_COLLECTION),
                [Minifig::THUMB_CONVERSION, Minifig::MEDIUM_CONVERSION, Minifig::LARGE_CONVERSION],
            );
        }

        return null;
    }
}
