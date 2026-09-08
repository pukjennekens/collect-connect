<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Product;

use App\Domain\Product\Services\StockService;
use App\Models\Color;
use App\Models\Order;
use App\Models\Part;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlStockCurrentReadTest extends TestCase
{
    use DatabaseMigrations { runDatabaseMigrations as private migrateTestDatabase; }

    public function runDatabaseMigrations(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->migrateTestDatabase();
        }
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

    public function test_mysql_current_read_includes_a_hold_committed_after_transaction_snapshot(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires MySQL REPEATABLE READ and two database connections.');
        }
        $product = $this->product();
        $connection = DB::getDefaultConnection();
        config(['database.connections.stock_concurrent' => config('database.connections.'.$connection)]);
        DB::beginTransaction();
        try {
            $this->assertSame(0, Order::query()->count());
            $order = new Order;
            $order->setConnection('stock_concurrent');
            $order->forceFill(Order::factory()->raw(['stock_held' => true]))->save();
            $order->items()->create(['product_id' => $product->id, 'title' => 'Brick', 'unit_price_cents' => 10, 'quantity' => 3]);
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->assertSame(0, Order::query()->count());
            $this->assertSame(3, app(StockService::class)->heldQuantity($locked));
        } finally {
            DB::rollBack();
            DB::purge('stock_concurrent');
        }
    }
}
