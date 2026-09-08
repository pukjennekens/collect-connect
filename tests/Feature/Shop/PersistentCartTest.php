<?php

declare(strict_types=1);

namespace Tests\Feature\Shop;

use App\Models\Part;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Binafy\LaravelCart\Models\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersistentCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_cart_survives_session_loss_and_removes_by_product_identity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->for(Part::factory(), 'productable')->create(['stock' => 12, 'price' => 250]);
        $other = Product::factory()->for(Part::factory(), 'productable')->create(['stock' => 12]);
        $this->actingAs($user);
        $cart = app(CartService::class);
        $cart->addItem($product, 4);
        $cart->addItem($other, 2);
        session()->flush();
        $this->assertCount(2, $cart->getRawItems());
        $cart->removeItem($other);
        $this->assertSame($product->id, $cart->getRawItems()[0]['itemable_id']);
        $this->assertSame(1000, $cart->getTotal());
        $cart->setQuantity($product, 30);
        $this->assertSame(12, $cart->getRawItems()[0]['quantity']);
        $cart->clear();
        $this->assertSame([], $cart->getRawItems());
    }

    public function test_guest_merge_is_capped_and_replayed_guest_session_is_not_merged_twice(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->for(Part::factory(), 'productable')->create(['stock' => 10]);
        $this->actingAs($user);
        $cart = app(CartService::class);
        $cart->addItem($product, 3);
        $guestId = (string) Str::uuid();
        $guest = ['cart_guest_id' => $guestId, 'cart_'.$guestId => [['itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => 9]]];
        session()->put($guest);
        $cart->mergeGuestCartIntoUser($user->id);
        $this->assertSame(10, $cart->getRawItems()[0]['quantity']);
        $cart->setQuantity($product, 2);
        session()->put($guest);
        $cart->mergeGuestCartIntoUser($user->id);
        $this->assertSame(2, $cart->getRawItems()[0]['quantity']);
        $this->assertFalse(session()->has('cart_guest_id'));
        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->count());
    }

    public function test_stock_revalidation_persists_correction_and_removes_inactive_products(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->for(Part::factory(), 'productable')->create(['stock' => 10]);
        $cart = app(CartService::class);
        $cart->addItem($product, 8);
        $product->update(['stock' => 3]);
        $this->assertNotEmpty($cart->revalidateStock());
        $this->assertSame(3, $cart->getRawItems()[0]['quantity']);
        $product->update(['is_active' => false]);
        $cart->revalidateStock();
        $this->assertSame([], $cart->getRawItems());
    }

    public function test_large_cart_reads_use_bounded_queries(): void
    {
        $user = User::factory()->create();
        $products = Product::factory()->count(100)->for(Part::factory(), 'productable')->create(['stock' => 10]);
        $stored = Cart::query()->create(['user_id' => $user->id]);
        foreach ($products as $product) {
            $stored->items()->create(['itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => 2]);
        }
        $this->actingAs($user);
        DB::enableQueryLog();
        $this->assertCount(100, app(CartService::class)->getItems());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(20, $queries);
    }

    public function test_guest_cart_stays_in_session_and_does_not_create_database_cart(): void
    {
        Auth::logout();
        $product = Product::factory()->for(Part::factory(), 'productable')->create(['stock' => 10]);
        $cart = app(CartService::class);
        $cart->addItem($product, 2);
        $this->assertSame(2, $cart->getRawItems()[0]['quantity']);
        $this->assertSame(0, Cart::query()->count());
    }
}
