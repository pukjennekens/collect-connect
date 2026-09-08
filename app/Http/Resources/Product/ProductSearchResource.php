<?php

declare(strict_types=1);

namespace App\Http\Resources\Product;

use App\Domain\Product\Queries\ProductListingQuery;
use App\Http\Resources\Color\ColorResource;
use App\Models\Part;
use App\Models\Product;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class ProductSearchResource extends ProductResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->loadMissing(ProductListingQuery::defaultWith());

        $siblings = $this->productable instanceof Part
            ? ($this->productable->products ?? collect())
            : collect();

        $priceMin = (int) ($siblings->min('price') ?? $this->price);
        $priceMax = (int) ($siblings->max('price') ?? $this->price);

        return [
            'id' => $this->id,
            'title' => $this->getTitle(),
            'lego_number' => $this->productable?->bricklink_id,
            'url' => route('product.show', $this->resource, absolute: false),
            'image' => $this->getImage(),
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
            'sibling_colors' => $siblings
                ->sortBy(fn (Product $product): string => $product->color->name ?? '')
                ->values()
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'stock' => $product->stock,
                    'price' => $product->price,
                    'image' => $product->productable instanceof Part ? $this->getPartImage($product->productable, $product->color_id) : null,
                    'color' => ColorResource::make($product->color)->resolve(),
                ])
                ->all(),
        ];
    }

    /**
     * A search card stands for the whole part, so it falls back to another
     * color's image when this product's own color has no media yet.
     */
    protected function getImage(): ?string
    {
        if ($this->productable instanceof Part) {
            return MediaUrl::forPartAnyColor($this->productable, (int) $this->color_id);
        }

        return parent::getImage();
    }
}
