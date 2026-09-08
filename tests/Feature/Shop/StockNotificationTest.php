<?php

declare(strict_types=1);

namespace Tests\Feature\Shop;

use App\Models\Color;
use App\Models\Part;
use App\Models\Product;
use App\Models\StockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_register_for_restock_email(): void
    {
        $part = Part::factory()->create();
        $color = Color::factory()->create();
        $product = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => $color->id,
            'stock' => 0,
        ]);

        $this->post(route('products.stock-notifications.store', $product), [
            'email' => 'buyer@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas(StockNotification::class, [
            'product_id' => $product->id,
            'email' => 'buyer@example.com',
        ]);
    }

    public function test_subscription_can_be_reactivated_without_duplicates(): void
    {
        $product = Product::factory()->for(Part::factory(), 'productable')->create(['stock' => 0]);
        $subscription = StockNotification::query()->create(['product_id' => $product->id, 'email' => 'buyer@example.com', 'notified_at' => now()]);
        $this->post(route('products.stock-notifications.store', $product), ['email' => 'buyer@example.com'])->assertRedirect();
        $this->post(route('products.stock-notifications.store', $product), ['email' => 'buyer@example.com'])->assertRedirect();
        $this->assertNull($subscription->fresh()->notified_at);
        $this->assertDatabaseCount('stock_notifications', 1);
    }

    public function test_notification_only_sends_once_after_restock(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $product = Product::factory()->for(Part::factory(), 'productable')->create(['stock' => 0]);
        $subscription = StockNotification::query()->create(['product_id' => $product->id, 'email' => 'buyer@example.com']);
        $job = new \App\Domain\Bricqer\Jobs\DispatchStockNotificationsJob([$product->id]);
        $job->handle();
        \Illuminate\Support\Facades\Mail::assertNothingSent();
        $product->update(['stock' => 2]);
        $job->handle();
        $job->handle();
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\StockBackInStockMail::class, 1);
        $this->assertNotNull($subscription->fresh()->notified_at);
    }
}
