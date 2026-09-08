<?php

declare(strict_types=1);

namespace App\Http\Resources\Minifig;

use App\Models\Minifig;
use App\Models\Product;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Minifig
 */
class MinifigSearchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->loadMissing(['media', 'products']);

        /** @var Product|null $product */
        $product = $this->products
            ->filter(fn (Product $candidate): bool => $candidate->stock > 0)
            ->sortByDesc('stock')
            ->first();

        $price = $product?->price;

        return [
            // Always prefer the sellable product id so cards open a product page.
            'id' => $product->id ?? $this->id,
            'title' => $this->name,
            'lego_number' => $this->bricklink_id,
            'rebrickable_id' => $this->rebrickable_id,
            'url' => $product !== null
                ? route('product.show', $product, absolute: false)
                : route('catalog.minifigs', absolute: false),
            'image' => $this->imageUrl(),
            'priceMin' => $price,
            'priceMax' => $price,
            'sibling_colors' => [],
            'type' => 'minifig',
        ];
    }

    protected function imageUrl(): ?string
    {
        return MediaUrl::forMinifig($this->resource, [Minifig::THUMB_CONVERSION]);
    }
}
