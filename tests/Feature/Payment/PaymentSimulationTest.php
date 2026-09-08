<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\Color;
use App\Models\Order;
use App\Models\Part;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSimulationTest extends TestCase
{
    use RefreshDatabase;

    private int $shippingMethodId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shippingMethodId = \App\Models\ShippingMethod::query()->create([
            'bricqer_id' => 1, 'name' => 'PostNL Brievenbus (NL)', 'is_active' => true,
            'country_regions' => ['NL' => 'NL'], 'country_ids' => ['NL' => 1],
            'rate_bands' => [['shipping_code' => 'NL', 'weight_min' => 0, 'weight_max' => 100000, 'price' => '3.95']],
        ])->id;
    }

    public function test_checkout_redirects_to_the_gateway_payment_page_when_one_is_offered(): void
    {
        config(['payment.default' => 'testing']);

        $product = $this->productInCart(2, 250);
        app(CartService::class)->addItem($product, 1);

        $response = $this->post(route('checkout.store'), $this->checkoutPayload());

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('checkout.payment.simulate', $order));
        $this->assertSame('testing', $order->payment_provider);
    }

    public function test_checkout_falls_back_to_the_confirmation_page_without_a_gateway_page(): void
    {
        config(['payment.default' => 'manual']);

        $product = $this->productInCart(2, 250);
        app(CartService::class)->addItem($product, 1);

        $response = $this->post(route('checkout.store'), $this->checkoutPayload());

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('checkout.confirmation', $order));
    }

    public function test_simulating_a_successful_payment_marks_the_order_paid(): void
    {
        config(['payment.default' => 'testing']);

        $order = Order::factory()->create();
        $this->withSession(['checkout.placed_order_ids' => [$order->id]]);

        $this->post(route('checkout.payment.simulate.store', $order), ['outcome' => 'paid'])
            ->assertRedirect(route('checkout.confirmation', $order));

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNull($order->stock_reserved_at);
    }

    public function test_simulating_a_failed_payment_leaves_the_order_pending(): void
    {
        config(['payment.default' => 'testing']);

        $order = Order::factory()->create();
        $this->withSession(['checkout.placed_order_ids' => [$order->id]]);

        $this->from(route('checkout.payment.simulate', $order))
            ->post(route('checkout.payment.simulate.store', $order), ['outcome' => 'failed'])
            ->assertRedirect(route('checkout.payment.simulate', $order));

        $order->refresh();
        $this->assertSame('pending_payment', $order->status);
        $this->assertNull($order->paid_at);
    }

    public function test_paying_twice_does_not_change_the_settled_order(): void
    {
        config(['payment.default' => 'testing']);

        $order = Order::factory()->create();
        $this->withSession(['checkout.placed_order_ids' => [$order->id]]);

        $this->post(route('checkout.payment.simulate.store', $order), ['outcome' => 'paid']);
        $paidAt = $order->refresh()->paid_at;

        $this->travel(5)->minutes();
        $this->post(route('checkout.payment.simulate.store', $order), ['outcome' => 'paid']);

        $this->assertTrue($paidAt->equalTo($order->refresh()->paid_at));
    }

    public function test_the_simulator_is_not_reachable_for_someone_elses_order(): void
    {
        $order = Order::factory()->create();

        $this->get(route('checkout.payment.simulate', $order))->assertForbidden();
        $this->post(route('checkout.payment.simulate.store', $order))->assertForbidden();

        $this->assertSame('pending_payment', $order->refresh()->status);
    }

    public function test_the_order_owner_can_reach_the_simulator_without_the_session_marker(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('checkout.payment.simulate', $order))
            ->assertOk();
    }

    public function test_the_simulator_is_unavailable_in_production(): void
    {
        $order = Order::factory()->create();
        $this->withSession(['checkout.placed_order_ids' => [$order->id]]);

        $this->app['env'] = 'production';

        $this->get(route('checkout.payment.simulate', $order))->assertNotFound();
        // CSRF stops bypassing itself once the environment is no longer "testing".
        $this->post(route('checkout.payment.simulate.store', $order), ['_token' => csrf_token()])
            ->assertNotFound();
    }

    private function productInCart(int $stock, int $priceCents): Product
    {
        $part = Part::factory()->create(['weight_grams' => 1]);

        return Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => Color::factory(),
            'stock' => $stock,
            'price' => $priceCents,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(): array
    {
        return [
            'name' => 'Test Buyer',
            'email' => 'buyer@example.com',
            'line1' => 'Straat 1',
            'postal_code' => '1234AB',
            'city' => 'Amsterdam',
            'country_code' => 'NL',
            'shipping_method_id' => $this->shippingMethodId,
            'payment_method' => 'ideal',
            'create_account' => false,
        ];
    }
}
