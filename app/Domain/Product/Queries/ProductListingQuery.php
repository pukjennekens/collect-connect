<?php

declare(strict_types=1);

namespace App\Domain\Product\Queries;

use App\Models\Minifig;
use App\Models\Part;
use App\Models\Product;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Shared eager-load strategy for Product + morph productable trees used by
 * catalog, product suggestions, set BOMs, and search hydration.
 */
final class ProductListingQuery
{
    /**
     * @return Builder<Product>
     */
    public static function base(): Builder
    {
        return Product::query()->with(self::defaultWith());
    }

    /**
     * @return array<int|string, string|Closure>
     */
    public static function defaultWith(): array
    {
        return [
            'color',
            'productable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Part::class => ['partColors.media', 'partCategory', 'products.color'],
                Minifig::class => ['media'],
            ]),
        ];
    }

    /**
     * @return array<int|string, string|Closure>
     */
    public static function forType(string $type): array
    {
        if ($type === 'minifig') {
            return [
                'color',
                'productable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    Minifig::class => ['media'],
                ]),
            ];
        }

        return [
            'color',
            'productable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Part::class => ['partColors.media', 'partCategory', 'products.color'],
            ]),
        ];
    }
}
