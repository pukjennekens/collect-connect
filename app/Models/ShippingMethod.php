<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    protected $fillable = [
        'bricqer_id',
        'name',
        'code',
        'area',
        'price_cents',
        'track_trace',
        'countries',
        'is_active',
        'rate_bands',
        'country_regions',
        'country_ids',
    ];

    /** @return array{track_trace: 'boolean', is_active: 'boolean', countries: 'array', rate_bands: 'array', country_regions: 'array', country_ids: 'array'} */
    protected function casts(): array
    {
        return [
            'track_trace' => 'boolean',
            'is_active' => 'boolean',
            'countries' => 'array',
            'rate_bands' => 'array',
            'country_regions' => 'array',
            'country_ids' => 'array',
        ];
    }
}
