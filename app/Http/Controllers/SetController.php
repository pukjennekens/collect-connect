<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Product\Queries\ProductListingQuery;
use App\Http\Resources\Set\SetResource;
use App\Models\Color;
use App\Models\Minifig;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Pivots\InventoryMinifig;
use App\Models\Pivots\InventoryPart;
use App\Models\Product;
use App\Models\Set;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Inertia\Response;

class SetController extends Controller
{
    public function show(Request $request, Set $set): Response
    {
        $set->load(['media', 'theme', 'latestInventory']);
        $categoryId = $request->integer('category_id') ?: null;
        $colorId = $request->integer('color_id') ?: null;
        $inventoryId = $set->latestInventory?->id;
        $parts = InventoryPart::query()->where('inventory_id', $inventoryId)
            ->with(['part.partColors.media', 'color'])
            ->when($colorId, fn ($query) => $query->where('color_id', $colorId))
            ->when($categoryId, fn ($query) => $query->whereHas('part', fn ($part) => $part->where('part_category_id', $categoryId)))
            ->orderBy('part_id')->orderBy('color_id')->orderBy('is_spare')
            ->paginate(48, ['*'], 'parts_page')->withQueryString();
        $minifigs = InventoryMinifig::query()->where('inventory_id', $inventoryId)->with('minifig.media')->orderBy('minifig_id')->paginate(24, ['*'], 'minifigs_page')->withQueryString();
        $products = Product::query()->where(function ($query) use ($parts, $minifigs): void {
            $query->where(function ($query) use ($parts): void {
                $query->where('productable_type', (new Part)->getMorphClass())->whereIn('productable_id', $parts->pluck('part_id'));
            })->orWhere(function ($query) use ($minifigs): void {
                $query->where('productable_type', (new Minifig)->getMorphClass())->whereIn('productable_id', $minifigs->pluck('minifig_id'));
            });
        })->with(ProductListingQuery::defaultWith())->get();
        $partItems = $parts->getCollection()->map(function (InventoryPart $row) use ($products): array {
            $part = $row->part;
            abort_if($part === null, 404);
            $product = $products->first(fn (Product $product): bool => $product->productable_type === (new Part)->getMorphClass() && $product->productable_id === $row->part_id && $product->color_id === $row->color_id);

            return $this->item($product, $part, $row->quantity, MediaUrl::forPart($part, $row->color_id), $row->color, (bool) $row->is_spare);
        });
        $minifigItems = $minifigs->getCollection()->map(function (InventoryMinifig $row) use ($products): array {
            $minifig = $row->minifig;
            abort_if($minifig === null, 404);
            $product = $products->first(fn (Product $product): bool => $product->productable_type === (new Minifig)->getMorphClass() && $product->productable_id === $row->minifig_id);

            return $this->item($product, $minifig, $row->quantity, MediaUrl::forMinifig($minifig));
        });
        $items = $partItems->merge($minifigItems);
        $allParts = InventoryPart::query()->where('inventory_id', $inventoryId);

        return inertia('sets/show', [
            'set' => SetResource::make($set),
            'theme' => $set->theme?->name,
            'parts' => [...$parts->toArray(), 'data' => $partItems],
            'minifigs' => [...$minifigs->toArray(), 'data' => $minifigItems],
            'in_stock_parts' => $items->filter(fn (array $item): bool => $item['stock'] > 0)->values(),
            'out_of_stock_parts' => $items->filter(fn (array $item): bool => $item['stock'] <= 0)->values(),
            'filters' => [
                'categories' => PartCategory::query()->whereIn('id', Part::query()->select('part_category_id')->whereIn('id', (clone $allParts)->select('part_id')))->orderBy('name')->get(['id', 'name']),
                'colors' => Color::query()->whereIn('id', (clone $allParts)->select('color_id'))->orderBy('name')->get(['id', 'name']),
            ],
            'active' => ['category_id' => $categoryId, 'color_id' => $colorId],
        ]);
    }

    /** @return array<string, mixed> */
    private function item(?Product $product, Part|Minifig $entity, int $quantity, ?string $image, ?Color $color = null, bool $spare = false): array
    {
        return [
            'id' => $product?->id,
            'title' => $product?->commerce_title ?: $entity->name,
            'lego_number' => $entity->bricklink_id ?: $entity->rebrickable_id,
            'image' => $image,
            'stock' => $product?->is_active ? $product->stock : 0,
            'price' => $product?->price,
            'url' => $product ? route('product.show', $product) : null,
            'color' => $color ? ['name' => $color->name, 'hex' => $color->hex] : null,
            'quantity_in_set' => $quantity,
            'is_spare' => $spare,
            'type' => $entity instanceof Part ? 'part' : 'minifig',
        ];
    }
}
