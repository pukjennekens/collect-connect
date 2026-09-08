<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Product\Queries\ProductListingQuery;
use App\Http\Resources\Product\ProductResource;
use App\Models\Color;
use App\Models\Minifig;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Response;

class CatalogController extends Controller
{
    public function parts(Request $request): Response
    {
        return $this->listing($request, type: 'part');
    }

    public function minifigs(Request $request): Response
    {
        return $this->listing($request, type: 'minifig');
    }

    public function search(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $categoryId = $request->integer('category_id') ?: null;
        $colorId = $request->integer('color_id') ?: null;

        $query = Product::query()
            ->with(ProductListingQuery::defaultWith())
            ->purchasable()
            ->when($colorId, fn (Builder $builder) => $builder->where('color_id', $colorId))
            ->when($categoryId, function (Builder $builder) use ($categoryId): void {
                $builder->whereHasMorph(
                    'productable',
                    [Part::class],
                    fn (Builder $part) => $part->where('part_category_id', $categoryId),
                );
            })
            ->when($q !== '', function (Builder $builder) use ($q): void {
                $builder->where(function (Builder $inner) use ($q): void {
                    $inner->whereHasMorph(
                        'productable',
                        [Part::class, Minifig::class],
                        function (Builder $productable) use ($q): void {
                            $productable->where(function (Builder $match) use ($q): void {
                                $match
                                    ->where('name', 'like', "%{$q}%")
                                    ->orWhere('bricklink_id', 'like', "%{$q}%");
                            });
                        },
                    );
                });
            })
            ->orderByDesc('stock');

        return $this->respondWithListing($query, [
            'title' => $q !== '' ? "Zoekresultaten voor “{$q}”" : 'Zoeken',
            'type' => 'search',
            'query' => $q,
            'filters' => [
                'categories' => PartCategory::query()->orderBy('name')->get(['id', 'name']),
                'colors' => Color::query()
                    ->where('bricklink_color_id', '!=', '0')
                    ->orderBy('name')

                    ->get(['id', 'name', 'hex']),
            ],
            'active' => [
                'category_id' => $categoryId,
                'color_id' => $colorId,
                'q' => $q,
            ],
        ]);
    }

    protected function listing(Request $request, string $type): Response
    {
        $morph = $type === 'minifig' ? Minifig::class : Part::class;
        $categoryId = $request->integer('category_id') ?: null;
        $colorId = $request->integer('color_id') ?: null;

        $query = Product::query()
            ->with(ProductListingQuery::forType($type))
            ->where('productable_type', (new $morph)->getMorphClass())
            ->purchasable()
            ->when($colorId, fn (Builder $builder) => $builder->where('color_id', $colorId))
            ->when($categoryId && $type === 'part', function (Builder $builder) use ($categoryId): void {
                $builder->whereHasMorph('productable', [Part::class], fn (Builder $part) => $part->where('part_category_id', $categoryId));
            })
            ->orderByDesc('stock');

        return $this->respondWithListing($query, [
            'title' => $type === 'minifig' ? 'Minifiguren' : ($categoryId ? (PartCategory::query()->whereKey($categoryId)->value('name') ?? 'Onderdelen') : 'Onderdelen'),
            'type' => $type,
            'query' => null,
            'filters' => [
                'categories' => $type === 'part'
                    ? PartCategory::query()->orderBy('name')->get(['id', 'name'])
                    : [],
                'colors' => $type === 'part'
                    ? Color::query()
                        ->where('bricklink_color_id', '!=', '0')
                        ->whereIn('id', Product::query()->select('color_id')->purchasable()->where('productable_type', (new Part)->getMorphClass()))
                        ->orderBy('name')

                        ->get(['id', 'name', 'hex'])
                    : [],
            ],
            'active' => [
                'category_id' => $categoryId,
                'color_id' => $colorId,
                'q' => null,
            ],
        ]);
    }

    /**
     * Paginate a product listing query and render the catalog page shared by
     * the search listing and the type-scoped listings.
     *
     * @param  Builder<Product>  $query
     * @param  array{title: string, type: string, query: ?string, filters: array<string, mixed>, active: array<string, mixed>}  $payload
     */
    protected function respondWithListing(Builder $query, array $payload): Response
    {
        $products = $query->paginate(48)->withQueryString();

        return inertia('shop/catalog', [
            ...$payload,
            'products' => ProductResource::collection($products),
        ]);
    }
}
