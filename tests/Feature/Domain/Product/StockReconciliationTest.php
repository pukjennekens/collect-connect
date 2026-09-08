<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Product;

use App\Domain\Bricqer\Jobs\SyncBricqerInventoryJob;
use App\Domain\Product\Services\StockService;
use App\Models\Color;
use App\Models\Order;
use App\Models\Part;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StockReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function product(): Product
    {
        $part = Part::factory()->create(['bricklink_id' => '3001']);
        $color = Color::factory()->create(['bricklink_color_id' => '5']);

        return Product::factory()->create([
            'productable_type' => $part->getMorphClass(), 'productable_id' => $part->id,
            'color_id' => $color->id, 'stock' => 7, 'bricqer_stock' => 10,
            'source_definitions' => [['definition_id' => 123, 'quantity' => 10]],
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function hold(Product $product, array $attributes = []): Order
    {
        $order = Order::factory()->create(['stock_held' => true, ...$attributes]);
        $order->items()->create([
            'product_id' => $product->id, 'title' => 'Brick', 'unit_price_cents' => 10,
            'quantity' => 3, 'source_allocations' => [['definition_id' => 123, 'quantity' => 3]],
        ]);

        return $order;
    }

    private function sync(int $quantity): void
    {
        $job = new class($quantity) extends SyncBricqerInventoryJob
        {
            public function __construct(private int $quantity) {}

            protected function consolidate(): array
            {
                return [[
                    'item_type' => 'P', 'item_id' => '3001', 'color_id' => '5',
                    'stock' => $this->quantity, 'price' => 10, 'definition_id' => '123',
                    'title' => 'Brick', 'definitions' => [123 => $this->quantity],
                ]];
            }
        };
        $job->handle();
    }

    public function test_export_and_successful_status_fetch_do_not_release_unpicked_stock(): void
    {
        $product = $this->product();
        $order = $this->hold($product, [
            'status' => 'paid', 'paid_at' => now()->subMinute(), 'stock_reserved_at' => null,
            'sync_status' => 'synced', 'exported_at' => now()->subMinute(),
            'fulfilment_status' => 'processing',
        ]);
        $this->sync(10);
        $this->assertTrue($order->fresh()->stock_held);
        $this->assertSame(7, $product->fresh()->stock);

        $order->forceFill(['fulfilment_status' => 'packed'])->save();
        $this->sync(7);
        $this->assertFalse($order->fresh()->stock_held);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_legacy_reservation_survives_sync_and_releases_without_counting_itself(): void
    {
        $product = $this->product();
        $order = $this->hold($product, ['stock_held' => false]);
        $this->sync(10);
        $this->assertSame(7, $product->fresh()->stock);
        DB::transaction(function () use ($order): void {
            app(StockService::class)->release(Order::query()->lockForUpdate()->findOrFail($order->id));
        });
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertNull($order->fresh()->stock_reserved_at);
        DB::transaction(fn () => app(StockService::class)->release($order->fresh()));
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_source_allocations_deduct_other_orders_before_reserving(): void
    {
        $product = $this->product();
        $this->hold($product);
        DB::transaction(function () use ($product): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->assertSame([['definition_id' => 123, 'quantity' => 7]], app(StockService::class)->reserve($locked, 7));
        });
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_invalid_source_quantities_block_reservation_without_changing_stock(): void
    {
        $product = $this->product();
        $product->forceFill(['source_definitions' => [['definition_id' => 123, 'quantity' => -1]]])->save();
        try {
            DB::transaction(fn () => app(StockService::class)->reserve(Product::query()->lockForUpdate()->findOrFail($product->id), 1));
            $this->fail('Invalid source quantity accepted.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }
        $this->assertSame(7, $product->fresh()->stock);
    }
}
