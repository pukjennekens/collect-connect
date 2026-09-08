<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    /** @use HasFactory<\Database\Factories\ThemeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['rebrickable_id', 'name', 'parent_id'];

    /** @return BelongsTo<Theme, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'rebrickable_id');
    }

    /** @return HasMany<Theme, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'rebrickable_id');
    }

    /** @return HasMany<Set, $this> */
    public function sets(): HasMany
    {
        return $this->hasMany(Set::class, 'theme_id', 'rebrickable_id');
    }
}
