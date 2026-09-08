<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Product\Queries\ProductListingQuery;
use App\Http\Resources\Product\ProductResource;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\TrendingProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Number of cards the homepage rows are designed for.
     */
    private const TRENDING_LIMIT = 5;

    private const CATEGORY_LIMIT = 4;

    public function __invoke(): Response
    {
        return inertia('index', [
            'trendingProducts' => ProductResource::collection($this->trendingProducts()),
            'popularCategories' => $this->popularCategories(),
        ]);
    }

    /**
     * Products hand-picked in the admin panel, in the order set there. Falls
     * back to the best-stocked products while nothing is curated yet.
     *
     * @return Collection<int, Product>
     */
    private function trendingProducts(): Collection
    {
        $curated = TrendingProduct::query()
            ->with(['product' => fn (Relation $product) => $product->with(ProductListingQuery::defaultWith())])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(self::TRENDING_LIMIT)
            ->get()
            ->map(fn (TrendingProduct $trending): ?Product => $trending->product)
            ->filter()
            ->values();

        if ($curated->isNotEmpty()) {
            return $curated;
        }

        return ProductListingQuery::base()
            ->where('stock', '>', 0)
            ->orderByDesc('stock')
            ->limit(self::TRENDING_LIMIT)
            ->get()
            ->toBase();
    }

    /**
     * Part categories with the most parts that are actually in stock.
     *
     * @return list<array{id: int, name: string, url: string}>
     */
    private function popularCategories(): array
    {
        $inStockParts = fn (Builder $part) => $part->whereHas(
            'products',
            fn (Builder $product) => $product->where('stock', '>', 0),
        );

        return array_values(PartCategory::query()
            ->withCount(['parts' => $inStockParts])
            ->whereHas('parts', $inStockParts)
            ->orderByDesc('parts_count')
            ->orderBy('name')
            ->limit(self::CATEGORY_LIMIT)
            ->get()
            ->map(fn (PartCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'url' => route('catalog.parts', ['category_id' => $category->id], absolute: false),
            ])
            ->values()
            ->all());
    }
}
