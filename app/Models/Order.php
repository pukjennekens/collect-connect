<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    /**
     * Customer-facing fields only. Lifecycle fields (status, payment, tracking,
     * stock reservation) are set via forceFill inside domain actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'number',
        'email',
        'name',
        'phone',
        'shipping_company',
        'shipping_line1',
        'shipping_line2',
        'shipping_postal_code',
        'shipping_city',
        'shipping_country_code',
        'subtotal_cents',
        'shipping_cents',
        'total_cents',
        'shipping_method_name',
        'shipping_method_id',
        'payment_method',
    ];

    /** @return array{paid_at: 'datetime', stock_reserved_at: 'datetime', meta: 'array', billing_address: 'array', shipping_address: 'array', stock_held: 'boolean', exported_at: 'datetime', synced_at: 'datetime'} */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'stock_reserved_at' => 'datetime',
            'meta' => 'array',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'stock_held' => 'boolean',
            'exported_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function paymentExpiresAt(): ?\Carbon\Carbon
    {
        return $this->stock_reserved_at?->copy()->addMinutes((int) config('orders.reservation_ttl_minutes', 60));
    }

    public function canPay(): bool
    {
        return $this->status === 'pending_payment'
            && $this->paid_at === null
            && $this->paymentExpiresAt()?->isFuture() === true;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
