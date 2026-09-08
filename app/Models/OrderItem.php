<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'title',
        'lego_number',
        'color_name',
        'unit_price_cents',
        'quantity',
        'bricqer_definition_id',
        'source_allocations',
    ];

    /** @return array{source_allocations: 'array', quantity: 'integer', unit_price_cents: 'integer'} */
    protected function casts(): array
    {
        return ['source_allocations' => 'array', 'quantity' => 'integer', 'unit_price_cents' => 'integer'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
