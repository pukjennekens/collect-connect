<?php

declare(strict_types=1);

namespace App\Http\Resources\Product;

use App\Domain\Product\Queries\ProductListingQuery;
use App\Http\Resources\Color\ColorResource;
use App\Models\Minifig;
use App\Models\Part;
use App\Models\Product;
use App\Support\MediaUrl;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->loadMissing(ProductListingQuery::defaultWith());

        $productable = $this->productable;

        return [
            'id' => $this->id,
            'stock' => $this->is_active ? $this->stock : 0,
            'purchasable' => $this->isPurchasable(),
            'price' => $this->price,
            'title' => $this->getTitle(),
            'image' => $this->getImage(),
            'lego_number' => $productable?->bricklink_id,
            'rebrickable_id' => $productable->rebrickable_id ?? null,
            'type' => $productable instanceof Minifig ? 'minifig' : 'part',
            'category' => $productable instanceof Part
                ? $productable->partCategory?->name
                : null,
            'color' => $this->shouldShowColor()
                ? ColorResource::make($this->color)
                : null,
            'url' => route('product.show', $this->resource, absolute: false),
            'attributes' => $this->attributesList(),
            'sibling_colors' => $productable instanceof Part
                ? $productable->products->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'stock' => $product->stock,
                    'price' => $product->price,
                    'image' => $this->getPartImage($productable, $product->color_id),
                    'color' => ColorResource::make($product->color),
                    'url' => route('product.show', $product, absolute: false),
                ])
                : [],
        ];
    }

    protected function getTitle(): string
    {
        if ($this->productable instanceof Part || $this->productable instanceof Minifig) {
            return (string) ($this->commerce_title ?: $this->productable->name);
        }

        throw new Exception('Productable type not found');
    }

    protected function getPartImage(Part $part, int $colorId): ?string
    {
        return MediaUrl::forPart($part, $colorId);
    }

    protected function getImage(): ?string
    {
        if ($this->productable instanceof Part) {
            return $this->getPartImage($this->productable, (int) $this->color_id);
        }

        if ($this->productable instanceof Minifig) {
            return $this->getMinifigImage($this->productable);
        }

        return null;
    }

    protected function getMinifigImage(Minifig $minifig): ?string
    {
        return MediaUrl::forMinifig($minifig);
    }

    protected function shouldShowColor(): bool
    {
        if ($this->productable instanceof Minifig) {
            return false;
        }

        return $this->color !== null
            && $this->color->bricklink_color_id !== '0'
            && $this->color->name !== '(Not Applicable)';
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    protected function attributesList(): array
    {
        $part = $this->productable instanceof Part ? $this->productable : null;

        $attrs = [
            ['label' => 'Type', 'value' => $this->productable instanceof Minifig ? 'Minifiguur' : 'Onderdeel'],
            ['label' => 'LEGO-nummer', 'value' => (string) ($this->productable->bricklink_id ?? '—')],
        ];

        if ($part?->rebrickable_id) {
            $attrs[] = ['label' => 'Rebrickable', 'value' => (string) $part->rebrickable_id];
        }

        if ($part?->partCategory) {
            $attrs[] = ['label' => 'Categorie', 'value' => (string) $part->partCategory->name];
        }

        if ($this->shouldShowColor() && $this->color) {
            $attrs[] = ['label' => 'Kleur', 'value' => (string) $this->color->name];
        }

        $weight = data_get($this->productable, 'weight_grams');
        if ($weight !== null) {
            $attrs[] = ['label' => 'Gewicht', 'value' => rtrim(rtrim(number_format((float) $weight, 4, '.', ''), '0'), '.').' g'];
        }

        $attrs[] = ['label' => 'Voorraad', 'value' => (string) $this->stock];

        return $attrs;
    }
}
