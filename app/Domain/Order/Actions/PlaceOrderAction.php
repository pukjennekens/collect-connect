<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Payment\Contracts\PaymentGateway;
use App\Domain\Product\Services\StockService;
use App\Domain\Shipping\ShippingMethodResolver;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceOrderAction
{
    public function __construct(private CartService $cartService, private ShippingMethodResolver $shipping, private PaymentGateway $payments, private StockService $stock) {}

    /** @param array<string, mixed> $data
     * @param  Collection<int, covariant array<string, mixed>>  $cartItems
     * @param  list<array{id:string,label:string}>  $allowedPaymentMethods
     */
    public function handle(array $data, Collection $cartItems, array $allowedPaymentMethods): Order
    {
        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Je winkelwagen is leeg.']);
        }
        if (! in_array($data['payment_method'], array_column($allowedPaymentMethods, 'id'), true)) {
            throw ValidationException::withMessages(['payment_method' => 'Betaalmethode niet beschikbaar voor dit land.']);
        }
        $order = DB::transaction(function () use ($data, $cartItems): Order {
            $user = Auth::check() ? User::query()->lockForUpdate()->findOrFail(Auth::id()) : null;
            $products = Product::query()->with(['productable', 'color'])->whereIn('id', $cartItems->pluck('id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $weight = 0.0;
            foreach ($cartItems as $item) {
                $product = $products->get($item['id']);
                if (! $product || ! $product->isPurchasable() || $product->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['cart' => 'De voorraad is gewijzigd. Controleer je winkelwagen.']);
                }
                if ((int) round($item['price'] * 100) !== $product->price) {
                    throw ValidationException::withMessages(['cart' => 'De prijs is gewijzigd. Controleer het overzicht opnieuw.']);
                }
                $unitWeight = $product->productable?->weight_grams;
                if ($unitWeight === null) {
                    throw ValidationException::withMessages(['shipping_method_id' => 'Het gewicht van een artikel ontbreekt. Neem contact op voor verzending.']);
                }
                $weight += $unitWeight * $item['quantity'];
            }
            $shipping = $this->shipping->resolve((int) $data['shipping_method_id'], strtoupper($data['country_code']), $weight);
            if (isset($data['reviewed_shipping_cents']) && $shipping['price_cents'] !== (int) $data['reviewed_shipping_cents']) {
                throw ValidationException::withMessages(['shipping_method_id' => 'De verzendkosten zijn gewijzigd. Controleer het overzicht opnieuw.']);
            }
            if (! $user && ($data['create_account'] ?? false)) {
                $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            }
            $address = collect($data)->only(['name', 'company', 'email', 'phone', 'line1', 'line2', 'house_number', 'house_addition', 'postal_code', 'city', 'country_code'])->all();
            $address['country_code'] = strtoupper($address['country_code']);
            if ($user && ($data['save_address'] ?? false)) {
                $user->addresses()->firstOrCreate($address, ['is_default' => ! $user->addresses()->exists()]);
            }
            $order = new Order;
            $order->forceFill([
                'checkout_token' => $data['checkout_token'], 'user_id' => $user?->id,
                'number' => 'C2C-'.Str::upper((string) Str::ulid()), 'status' => 'pending_payment',
                'email' => $data['email'], 'name' => $data['name'], 'phone' => $data['phone'] ?? null,
                'shipping_company' => $data['company'] ?? null,
                'shipping_line1' => trim($data['line1'].' '.($data['house_number'] ?? '').($data['house_addition'] ?? '')),
                'shipping_line2' => $data['line2'] ?? null, 'shipping_postal_code' => $data['postal_code'],
                'shipping_city' => $data['city'], 'shipping_country_code' => $address['country_code'],
                'shipping_address' => $address,
                'billing_address' => $address,
                'shipping_cents' => $shipping['price_cents'], 'subtotal_cents' => 0, 'total_cents' => 0,
                'shipping_method_name' => $shipping['name'], 'shipping_method_id' => $shipping['id'],
                'payment_method' => $data['payment_method'], 'payment_status' => 'pending',
                'stock_held' => true, 'stock_reserved_at' => now(),
            ])->save();
            $subtotal = 0;
            foreach ($cartItems->sortBy('id') as $item) {
                $product = $products->get($item['id']);
                assert($product instanceof Product && $product->productable !== null);
                $allocations = $this->stock->reserve($product, (int) $item['quantity']);
                $subtotal += $product->price * $item['quantity'];
                OrderItem::query()->create([
                    'order_id' => $order->id, 'product_id' => $product->id,
                    'title' => $product->commerce_title ?: $product->productable->name,
                    'lego_number' => $product->productable->bricklink_id, 'color_name' => $product->color?->name,
                    'unit_price_cents' => $product->price, 'quantity' => $item['quantity'],
                    'source_allocations' => $allocations, 'bricqer_definition_id' => $allocations[0]['definition_id'] ?? null,
                ]);
            }
            $order->forceFill(['subtotal_cents' => $subtotal, 'total_cents' => $subtotal + $shipping['price_cents']])->save();

            return $order;
        }, 3);

        $placed = session('checkout.placed_order_ids', []);
        session(['checkout.placed_order_ids' => array_values(array_unique([...$placed, $order->id]))]);
        $this->cartService->clear();
        if (! Auth::check() && $order->user_id) {
            Auth::loginUsingId($order->user_id);
            session()->regenerate();
        }

        return $this->initiatePayment($order);
    }

    public function initiatePayment(Order $order): Order
    {
        if ($order->status !== 'pending_payment' || filled(data_get($order->meta, 'redirect_url')) || $order->paid_at !== null) {
            return $order;
        }
        $payment = $this->payments->initiate($order);
        $order->forceFill(['payment_provider' => $payment->provider, 'payment_reference' => $payment->reference,
            'meta' => [...($order->meta ?? []), 'redirect_url' => $payment->redirectUrl, 'simulated' => $payment->provider === 'testing'],
        ])->save();

        return $order->refresh();
    }
}
