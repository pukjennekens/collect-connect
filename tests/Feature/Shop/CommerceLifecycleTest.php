<?php

declare(strict_types=1);

namespace Tests\Feature\Shop;

use App\Domain\Bricqer\Jobs\ExportBricqerOrderJob;
use App\Domain\Bricqer\Jobs\SyncBricqerInventoryJob;
use App\Domain\Order\Jobs\ReleaseUnpaidOrderStockJob;
use App\Integrations\Bricqer\Requests\Lego\Report\GetUnconsolidatedInventoryRequest;
use App\Mail\OrderPaidMail;
use App\Models\Color;
use App\Models\Order;
use App\Models\Part;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class CommerceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.default' => 'testing', 'bricqer.orders_enabled' => true, 'bricqer.domain' => 'test.bricqer.com', 'bricqer.api_key' => 'fake', 'orders.reservation_ttl_minutes' => 60]);
        Bus::fake();
        Queue::fake();
        Mail::fake();
    }

    public function test_checkout_retry_is_idempotent_and_uses_final_totals(): void
    {
        $product = $this->product();
        app(CartService::class)->addItem($product, 3);
        $payload = $this->payload();
        $this->post('/checkout', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::query()->sole();
        $this->assertSame(750, $order->subtotal_cents);
        $this->assertSame(425, $order->shipping_cents);
        $this->assertSame(1175, $order->total_cents);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertEquals([['definition_id' => 101, 'quantity' => 3]], $order->items()->sole()->source_allocations);
        $this->post('/checkout', $payload)->assertRedirect(route('checkout.payment.simulate', $order));
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_browser_checkout_ignores_hidden_billing_fields_and_uses_shipping_snapshot(): void
    {
        $product = $this->product();
        app(CartService::class)->addItem($product, 1);
        $payload = [...$this->payload(), 'billing_same_as_shipping' => true,
            'billing' => ['name' => '', 'line1' => '', 'postal_code' => '', 'city' => '', 'country_code' => 'NL']];
        $this->post('/checkout', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::query()->sole();
        $this->assertEquals($order->shipping_address, $order->billing_address);
    }

    public function test_simulator_reports_an_expired_reservation_as_not_payable(): void
    {
        $product = $this->product();
        app(CartService::class)->addItem($product, 1);
        $this->post('/checkout', $this->payload())->assertSessionHasNoErrors();
        $order = Order::query()->sole();
        $order->forceFill(['stock_reserved_at' => now()->subMinutes(61)])->save();
        $this->get(route('checkout.payment.simulate', $order))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('checkout/simulate-payment')->where('order.can_pay', false));
        $this->assertFalse($order->canPay());
    }

    public function test_bricqer_sync_preserves_reservation_and_expiry_releases_only_once(): void
    {
        $product = $this->product();
        app(CartService::class)->addItem($product, 3);
        $this->post('/checkout', $this->payload())->assertSessionHasNoErrors();
        $order = Order::query()->sole();
        $this->syncStock(8);
        $this->assertSame(8, $product->fresh()->bricqer_stock);
        $this->assertSame(5, $product->fresh()->stock);
        $order->forceFill(['stock_reserved_at' => now()->subMinutes(61)])->save();
        $this->assertSame(1, (new ReleaseUnpaidOrderStockJob)->handle()['released']);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(8, $product->fresh()->bricqer_stock);
        $this->assertSame(0, (new ReleaseUnpaidOrderStockJob)->handle()['released']);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_duplicate_success_dispatches_one_export_and_one_confirmation(): void
    {
        $product = $this->product();
        app(CartService::class)->addItem($product, 2);
        $this->post('/checkout', $this->payload())->assertSessionHasNoErrors();
        $order = Order::query()->sole();
        $url = route('checkout.payment.simulate.store', $order);
        $this->post($url, ['outcome' => 'paid'])->assertSessionHasNoErrors();
        $this->post($url, ['outcome' => 'paid'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertTrue($order->fresh()->stock_held);
        Bus::assertDispatchedTimes(ExportBricqerOrderJob::class, 1);
        Mail::assertQueued(OrderPaidMail::class, 1);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_expired_payment_is_rejected_without_resurrecting_stock_or_order(): void
    {
        $product = $this->product();
        app(CartService::class)->addItem($product, 2);
        $this->post('/checkout', $this->payload())->assertSessionHasNoErrors();
        $order = Order::query()->sole();
        $order->forceFill(['stock_reserved_at' => now()->subMinutes(61)])->save();
        (new ReleaseUnpaidOrderStockJob)->handle();
        $this->post(route('checkout.payment.simulate.store', $order), ['outcome' => 'paid'])->assertSessionHasErrors('payment');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame(10, $product->fresh()->stock);
        Bus::assertNotDispatched(ExportBricqerOrderJob::class);
    }

    public function test_checkout_requires_imported_shipping_and_known_weight(): void
    {
        $product = $this->product();
        app(CartService::class)->addItem($product, 1);
        $payload = $this->payload();
        ShippingMethod::query()->delete();
        $this->post('/checkout', $payload)->assertSessionHasErrors('shipping_method_id');
        $this->assertSame(0, Order::query()->count());
        $payload = $this->payload();
        $product->productable->update(['weight_grams' => null]);
        $this->post('/checkout', $payload)->assertSessionHasErrors('shipping_method_id');
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_billing_and_shipping_are_snapshots_and_saving_address_is_optional(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->product();
        app(CartService::class)->addItem($product, 1);
        $payload = [...$this->payload(), 'save_address' => true, 'billing_same_as_shipping' => false,
            'billing' => ['name' => 'Billing customer', 'line1' => 'Billing street', 'postal_code' => '5678AB', 'city' => 'Utrecht', 'country_code' => 'NL']];
        $this->post('/checkout', $payload)->assertSessionHasNoErrors();
        $order = Order::query()->sole();
        $this->assertSame('Shipping street', $order->billing_address['line1']);
        $this->assertSame('Shipping street', $order->shipping_address['line1']);
        $this->assertSame(1, $user->addresses()->count());
        $user->addresses()->sole()->update(['line1' => 'Changed saved address']);
        $this->assertSame('Shipping street', $order->fresh()->shipping_address['line1']);
        app(CartService::class)->addItem($product->fresh(), 1);
        $this->get('/checkout')->assertOk();
        $this->post('/checkout', [...$payload, 'save_address' => false])->assertSessionHasNoErrors();
        $this->assertSame(1, $user->addresses()->count());
    }

    public function test_price_or_quantity_changes_since_review_require_reconfirmation(): void
    {
        $product = $this->product();
        app(CartService::class)->addItem($product, 3);
        $payload = $this->payload();
        $this->get('/checkout')->assertOk();
        $product->update(['price' => 300]);
        $this->post('/checkout', $payload)->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::query()->count());
        $this->get('/checkout')->assertOk();
        $product->update(['stock' => 1]);
        $this->post('/checkout', $payload)->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::query()->count());
    }

    private function product(): Product
    {
        $part = Part::factory()->create(['bricklink_id' => '3001', 'weight_grams' => 10]);
        $color = Color::factory()->create(['bricklink_color_id' => '5']);

        return Product::factory()->for($part, 'productable')->create(['color_id' => $color->id, 'stock' => 10, 'bricqer_stock' => 10, 'price' => 250, 'source_definitions' => [['definition_id' => 101, 'quantity' => 10]]]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $shipping = ShippingMethod::query()->firstOrCreate(['bricqer_id' => 99], ['name' => 'Tracked delivery', 'is_active' => true, 'price_cents' => 9999, 'country_regions' => ['NL' => 'NL'], 'rate_bands' => [['shipping_code' => 'NL', 'weight_min' => 0, 'weight_max' => 1000, 'price' => '4.25']]]);

        return ['name' => 'Customer', 'email' => 'customer@example.com', 'line1' => 'Shipping street', 'postal_code' => '1234AB', 'city' => 'Amsterdam', 'country_code' => 'NL', 'shipping_method_id' => $shipping->id, 'payment_method' => 'ideal'];
    }

    private function syncStock(int $stock): void
    {
        $row = ['Definition ID' => 101, 'Item Type' => 'P', 'Item ID' => '3001', 'Color ID' => '5', 'Condition' => 'N', 'Remaining quantity' => $stock, 'Price' => 2.50, 'Description' => 'Brick'];
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, array_keys($row), escape: '');
        fputcsv($stream, array_values($row), escape: '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        Saloon::fake([GetUnconsolidatedInventoryRequest::class => MockResponse::make(body: $csv, headers: ['Content-Type' => 'text/csv'])]);
        (new SyncBricqerInventoryJob)->handle();
    }
}
