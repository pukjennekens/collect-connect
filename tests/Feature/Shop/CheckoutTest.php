<?php

declare(strict_types=1);

namespace Tests\Feature\Shop;

use App\Models\Color;
use App\Models\Order;
use App\Models\Part;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
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

    public function test_guest_can_place_order_from_cart(): void
    {
        $part = Part::factory()->create(['weight_grams' => 1]);
        $color = Color::factory()->create();
        $product = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => $color->id,
            'stock' => 10,
            'price' => 250,
        ]);

        app(CartService::class)->addItem($product, 2);

        $response = $this->post(route('checkout.store'), [
            'name' => 'Test Buyer',
            'email' => 'buyer@example.com',
            'phone' => '0612345678',
            'line1' => 'Straat 1',
            'line2' => null,
            'postal_code' => '1234AB',
            'city' => 'Amsterdam',
            'country_code' => 'NL',
            'shipping_method_id' => $this->shippingMethodId,
            'payment_method' => 'ideal',
            'create_account' => false,
        ]);

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame(2, (int) $order->items()->sum('quantity'));
        $this->assertSame(8, $product->refresh()->stock);
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame(395, $order->shipping_cents);
        $this->assertSame(500 + 395, $order->total_cents);
    }

    public function test_client_cannot_underpay_shipping(): void
    {
        $part = Part::factory()->create(['weight_grams' => 1]);
        $product = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => Color::factory(),
            'stock' => 5,
            'price' => 100,
        ]);

        app(CartService::class)->addItem($product, 1);

        $this->post(route('checkout.store'), [
            'name' => 'Cheap Buyer',
            'email' => 'cheap@example.com',
            'line1' => 'Straat 1',
            'postal_code' => '1234AB',
            'city' => 'Amsterdam',
            'country_code' => 'NL',
            'shipping_method_id' => $this->shippingMethodId,
            // Attempt to force free shipping via client payload (ignored).
            'shipping_cents' => 0,
            'shipping_method_name' => 'Free hack',
            'payment_method' => 'bank',
            'create_account' => false,
        ])->assertRedirect();

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(395, $order->shipping_cents);
        $this->assertSame('PostNL Brievenbus (NL)', $order->shipping_method_name);
        $this->assertSame('pending_payment', $order->status);
    }

    public function test_guest_cannot_view_another_orders_confirmation(): void
    {
        $order = Order::query()->create([
            'number' => 'C2C-TEST-1',
            'status' => 'pending_payment',
            'email' => 'a@example.com',
            'name' => 'A',
            'shipping_line1' => 'Street',
            'shipping_postal_code' => '1234AB',
            'shipping_city' => 'Amsterdam',
            'shipping_country_code' => 'NL',
            'subtotal_cents' => 100,
            'shipping_cents' => 395,
            'total_cents' => 495,
            'shipping_method_name' => 'PostNL',
            'payment_method' => 'ideal',
            'payment_provider' => 'manual',
        ]);

        $this->get(route('checkout.confirmation', $order))->assertForbidden();
    }

    public function test_guest_can_view_confirmation_for_order_placed_in_session(): void
    {
        $part = Part::factory()->create(['weight_grams' => 1]);
        $product = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => Color::factory(),
            'stock' => 3,
            'price' => 200,
        ]);

        app(CartService::class)->addItem($product, 1);

        $this->post(route('checkout.store'), [
            'name' => 'Session Buyer',
            'email' => 'session@example.com',
            'line1' => 'Straat 1',
            'postal_code' => '1234AB',
            'city' => 'Amsterdam',
            'country_code' => 'NL',
            'shipping_method_id' => $this->shippingMethodId,
            'payment_method' => 'bank',
            'create_account' => false,
        ])->assertRedirect();

        $order = Order::query()->first();
        $this->assertNotNull($order);

        $this->get(route('checkout.confirmation', $order))->assertOk();
    }

    public function test_order_uses_locked_database_prices_not_client_payload(): void
    {
        $part = Part::factory()->create(['weight_grams' => 1]);
        $product = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => Color::factory(),
            'stock' => 5,
            'price' => 999,
        ]);

        app(CartService::class)->addItem($product, 2);

        $this->post(route('checkout.store'), [
            'name' => 'Price Hacker',
            'email' => 'hack@example.com',
            'line1' => 'Straat 1',
            'postal_code' => '1234AB',
            'city' => 'Amsterdam',
            'country_code' => 'NL',
            'shipping_method_id' => $this->shippingMethodId,
            'payment_method' => 'bank',
            'create_account' => false,
            // Spoofed line items must never affect totals.
            'items' => [
                ['id' => $product->id, 'price' => 0.01, 'quantity' => 2],
            ],
            'subtotal_cents' => 1,
            'total_cents' => 1,
        ])->assertRedirect();

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(1998, $order->subtotal_cents);
        $this->assertSame(1998 + 395, $order->total_cents);
        $this->assertSame(999, $order->items()->first()->unit_price_cents);
        $this->assertNotNull($order->stock_reserved_at);
    }

    public function test_checkout_can_create_account_and_attach_order(): void
    {
        $part = Part::factory()->create(['weight_grams' => 1]);
        $product = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => Color::factory(),
            'stock' => 4,
            'price' => 150,
        ]);

        app(CartService::class)->addItem($product, 1);

        $this->post(route('checkout.store'), [
            'name' => 'New Customer',
            'email' => 'customer@example.com',
            'line1' => 'Straat 1',
            'postal_code' => '1234AB',
            'city' => 'Amsterdam',
            'country_code' => 'NL',
            'shipping_method_id' => $this->shippingMethodId,
            'payment_method' => 'ideal',
            'create_account' => true,
            'save_address' => true,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect();

        $this->assertAuthenticated();
        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertNotNull($order->user_id);
        $this->assertSame('customer@example.com', $order->email);
    }

    public function test_checkout_persists_the_optional_company_field(): void
    {
        $part = Part::factory()->create(['weight_grams' => 1]);
        $product = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => Color::factory(),
            'stock' => 3,
            'price' => 100,
        ]);

        app(CartService::class)->addItem($product, 1);

        $this->post(route('checkout.store'), [
            'name' => 'Company Buyer',
            'email' => 'company@example.com',
            'company' => 'Pixel One B.V.',
            'line1' => 'Straat 1',
            'postal_code' => '1234AB',
            'city' => 'Amsterdam',
            'country_code' => 'NL',
            'shipping_method_id' => $this->shippingMethodId,
            'payment_method' => 'ideal',
            'create_account' => true,
            'save_address' => true,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('Pixel One B.V.', $order->shipping_company);
        $this->assertSame('Pixel One B.V.', $order->user->addresses()->firstOrFail()->company);
    }

    public function test_checkout_company_field_stays_optional(): void
    {
        $part = Part::factory()->create(['weight_grams' => 1]);
        $product = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => Color::factory(),
            'stock' => 3,
            'price' => 100,
        ]);

        app(CartService::class)->addItem($product, 1);

        $this->post(route('checkout.store'), [
            'name' => 'Private Buyer',
            'email' => 'private@example.com',
            'line1' => 'Straat 1',
            'postal_code' => '1234AB',
            'city' => 'Amsterdam',
            'country_code' => 'NL',
            'shipping_method_id' => $this->shippingMethodId,
            'payment_method' => 'ideal',
            'create_account' => false,
        ])->assertRedirect();

        $this->assertNull(Order::query()->firstOrFail()->shipping_company);
    }

    public function test_checkout_rejects_invalid_payment_method_for_country(): void
    {
        $part = Part::factory()->create(['weight_grams' => 1]);
        $product = Product::factory()->create([
            'productable_type' => $part->getMorphClass(),
            'productable_id' => $part->id,
            'color_id' => Color::factory(),
            'stock' => 2,
            'price' => 100,
        ]);

        app(CartService::class)->addItem($product, 1);

        $this->from(route('checkout.show'))
            ->post(route('checkout.store'), [
                'name' => 'NL Buyer',
                'email' => 'nl@example.com',
                'line1' => 'Straat 1',
                'postal_code' => '1234AB',
                'city' => 'Amsterdam',
                'country_code' => 'NL',
                'shipping_method_id' => $this->shippingMethodId,
                'payment_method' => 'bancontact',
                'create_account' => false,
            ])
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount(Order::class, 0);
    }
}
