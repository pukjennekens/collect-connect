<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Product\Queries\ProductListingQuery;
use App\Http\Resources\Product\ProductResource;
use App\Http\Resources\Set\SetResource;
use App\Models\Minifig;
use App\Models\Part;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Http\Request;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Request $request, Product $product): Response
    {
        $product->load(ProductListingQuery::defaultWith());

        $suggestions = collect();

        if ($product->productable instanceof Part) {
            $bricklinkId = str($product->productable->bricklink_id)
                ->replaceMatches('/[a-zA-Z].*/', '')
                ->toString();

            $suggestions = Product::query()
                ->whereMorphedTo('productable', Part::class)
                ->whereHas('productable', function ($query) use ($bricklinkId): void {
                    $query->where('bricklink_id', 'like', "{$bricklinkId}%");
                })
                ->where('id', '!=', $product->id)
                ->where('productable_id', '!=', $product->productable_id)
                ->purchasable()
                ->with(ProductListingQuery::forType('part'))
                ->limit(10)
                ->get()
                ->unique(fn (Product $product): string => "{$product->productable_type}-{$product->productable_id}");
        }

        if ($product->productable instanceof Minifig) {
            $suggestions = Product::query()
                ->whereMorphedTo('productable', Minifig::class)
                ->where('id', '!=', $product->id)
                ->purchasable()
                ->with(ProductListingQuery::forType('minifig'))
                ->orderByDesc('stock')
                ->limit(10)
                ->get();
        }

        $relatedSets = Set::query()->with('media')->whereHas('latestInventory', function ($inventory) use ($product): void {
            if ($product->productable instanceof Part) {
                $inventory->whereHas('parts', fn ($parts) => $parts->where('parts.id', $product->productable_id)->where('inventory_parts.color_id', $product->color_id));
            } else {
                $inventory->whereHas('minifigs', fn ($minifigs) => $minifigs->where('minifigs.id', $product->productable_id));
            }
        })->orderByDesc('year')->paginate(12, pageName: 'sets_page')->withQueryString();

        return inertia('shop/product', [
            'product' => ProductResource::make($product),
            'suggestions' => ProductResource::collection($suggestions),
            'related_sets' => SetResource::collection($relatedSets),
        ]);
    }
}
