<?php

declare(strict_types=1);

namespace App\Models;

use Binafy\LaravelCart\Cartable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Scout\Searchable;

class Product extends Model implements Cartable
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, Searchable;

    protected $fillable = [
        'productable_type',
        'productable_id',
        'stock',
        'price',
        'color_id',
        'is_active',
        'bricqer_stock',
        'source_definitions',
        'commerce_title',
    ];

    protected $attributes = ['is_active' => true];

    /** @return array{is_active: 'boolean', source_definitions: 'array', stock: 'integer', price: 'integer', bricqer_stock: 'integer'} */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'source_definitions' => 'array', 'stock' => 'integer', 'price' => 'integer', 'bricqer_stock' => 'integer'];
    }

    public function isPurchasable(): bool
    {
        return $this->is_active && $this->stock > 0 && $this->productable !== null;
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('stock', '>', 0);
    }

    /**
     * @return MorphTo<Part|Minifig, $this>
     */
    public function productable(): MorphTo
    {
        /** @var MorphTo<Part|Minifig, $this> $relationship */
        $relationship = $this->morphTo('productable');

        return $relationship;
    }

    /**
     * @return BelongsTo<Color, $this>
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function getPrice(): float
    {
        return $this->price / 100;
    }

    /**
     * The searchable data for the product. The name and BrickLink number are
     * pulled from the polymorphic productable (a part or a minifig).
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(self::searchEagerLoad());

        $productable = $this->productable;
        $type = $productable instanceof Minifig ? 'minifig' : 'part';
        $categoryId = $productable instanceof Part ? $productable->part_category_id : null;
        $categoryName = $productable instanceof Part
            ? ($productable->partCategory->name ?? '')
            : '';

        return [
            'id' => (string) $this->id,
            'name' => (string) ($productable->name ?? ''),
            'bricklink_id' => (string) data_get($productable, 'bricklink_id', ''),
            'price' => (int) $this->price,
            'stock' => (int) $this->stock,
            'type' => $type,
            'color_id' => (int) ($this->color_id ?? 0),
            'color_name' => (string) ($this->color->name ?? ''),
            'category_id' => (int) ($categoryId ?? 0),
            'category_name' => (string) $categoryName,
        ];
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(self::searchEagerLoad());
    }

    /**
     * Eager-load shape used when building the Scout search index payload.
     *
     * @return array<int|string, mixed>
     */
    private static function searchEagerLoad(): array
    {
        return [
            'color',
            'productable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Part::class => ['partCategory'],
            ]),
        ];
    }
}
