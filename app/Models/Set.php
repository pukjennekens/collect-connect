<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pivots\InventorySet;
use Database\Factories\SetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Set extends Model implements HasMedia
{
    /** @use HasFactory<SetFactory> */
    use HasFactory, InteractsWithMedia, Searchable;

    public const string IMAGE_COLLECTION = 'image';

    public const string THUMB_CONVERSION = 'thumb';

    public const string MEDIUM_CONVERSION = 'medium';

    public const string LARGE_CONVERSION = 'large';

    protected $fillable = [
        'set_num',
        'name',
        'year',
        'theme_id',
        'num_parts',
        'img_url',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE_COLLECTION)
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::THUMB_CONVERSION)
            ->performOnCollections(self::IMAGE_COLLECTION)
            ->fit(Fit::Contain, 200, 200)
            ->format('webp');

        $this->addMediaConversion(self::MEDIUM_CONVERSION)
            ->performOnCollections(self::IMAGE_COLLECTION)
            ->fit(Fit::Contain, 400, 400)
            ->format('webp');

        $this->addMediaConversion(self::LARGE_CONVERSION)
            ->performOnCollections(self::IMAGE_COLLECTION)
            ->fit(Fit::Contain, 800, 800)
            ->format('webp');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Theme, $this> */
    public function theme(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id', 'rebrickable_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<Inventory, $this> */
    public function latestInventory(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Inventory::class)->ofMany(['version' => 'max', 'id' => 'max']);
    }

    /** @return HasMany<Inventory, $this> */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Inventories that contain this set as a sub-set.
     *
     * @return BelongsToMany<Inventory, $this, InventorySet>
     */
    public function parentInventories(): BelongsToMany
    {
        return $this
            ->belongsToMany(Inventory::class, 'inventory_sets')
            ->using(InventorySet::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'set_num' => (string) $this->set_num,
            'name' => (string) $this->name,
        ];
    }
}
