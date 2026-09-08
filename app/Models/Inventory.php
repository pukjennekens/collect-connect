<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pivots\InventoryMinifig;
use App\Models\Pivots\InventoryPart;
use App\Models\Pivots\InventorySet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Inventory extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'rebrickable_id',
        'set_id',
        'version',
    ];

    /**
     * @return BelongsTo<Set, $this>
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class);
    }

    /**
     * @return BelongsToMany<Part, $this, InventoryPart>
     */
    public function parts(): BelongsToMany
    {
        return $this
            ->belongsToMany(Part::class, 'inventory_parts')
            ->withPivot(['color_id', 'quantity', 'is_spare'])->using(InventoryPart::class);
    }

    /**
     * @return BelongsToMany<Minifig, $this, InventoryMinifig>
     */
    public function minifigs(): BelongsToMany
    {
        return $this
            ->belongsToMany(Minifig::class, 'inventory_minifigs')
            ->withPivot('quantity')->using(InventoryMinifig::class);
    }

    /**
     * @return BelongsToMany<Set, $this, InventorySet>
     */
    public function sets(): BelongsToMany
    {
        return $this
            ->belongsToMany(Set::class, 'inventory_sets')
            ->using(InventorySet::class);
    }
}
