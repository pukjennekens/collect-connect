<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Order\Actions\PlaceOrderAction;
use App\Domain\Shipping\ShippingMethodResolver;
use App\Http\Requests\Checkout\StoreCheckoutRequest;
use App\Http\Resources\Order\OrderResource;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private ShippingMethodResolver $shipping,
    ) {}

    public function show(): Response|RedirectResponse
    {
        $this->cartService->revalidateStock();
        $items = $this->cartService->getItems();

        if ($items->isEmpty()) {
            return redirect()->route('cart.show');
        }

        $user = Auth::user();
        $address = $user?->addresses()->where('is_default', true)->first();
        $country = strtoupper((string) request()->query('country', data_get($address, 'country_code', 'NL')));

        try {
            $weight = $items->contains(fn (array $item): bool => $item['weight_grams'] === null) ? null : (float) $items->sum(fn (array $item): float => $item['weight_grams'] * $item['quantity']);
            $shippingMethods = $this->shipping->availableForCountry($country, $weight);
        } catch (ValidationException $exception) {
            $shippingMethods = [];
        }

        if (! session('checkout.token') || Order::query()->where('checkout_token', session('checkout.token'))->exists()) {
            session(['checkout.token' => (string) Str::uuid()]);
        }
        session(['checkout.review' => $this->fingerprint($items), 'checkout.shipping' => collect($shippingMethods)->pluck('price_cents', 'id')->all()]);

        return inertia('checkout/index', [
            'checkoutToken' => session('checkout.token'),
            'addresses' => $user?->addresses()->orderByDesc('is_default')->get() ?? [],
            'countries' => ShippingMethod::query()->where('is_active', true)->get()->flatMap(fn (ShippingMethod $method): array => array_keys($method->country_regions ?? []))->unique()->values(),
            'shippingError' => $shippingMethods === [] ? 'Geen passende verzendmethode beschikbaar voor het land en artikelgewicht.' : null,
            'items' => $items,
            'subtotal' => $items->sum(fn (array $i): float => $i['price'] * $i['quantity']),
            'weight_grams' => $this->cartService->getTotalWeightGrams(),
            'shippingMethods' => $shippingMethods,
            'paymentMethods' => $this->paymentMethodsForCountry($country),
            'defaultAddress' => $address,
            'user' => $user ? ['name' => $user->name, 'email' => $user->email] : null,
            'country' => $country,
        ]);
    }

    public function store(StoreCheckoutRequest $request, PlaceOrderAction $placeOrder): RedirectResponse
    {
        $token = $request->validated('checkout_token') ?? session('checkout.token') ?? (string) Str::uuid();
        if (! session('checkout.token')) {
            session(['checkout.token' => $token]);
        }

        return \Illuminate\Support\Facades\Cache::lock('checkout:'.$token, 120)->block(10, fn (): RedirectResponse => $this->storeOnce($request, $placeOrder));
    }

    private function storeOnce(StoreCheckoutRequest $request, PlaceOrderAction $placeOrder): RedirectResponse
    {
        $validated = $request->validated();
        $token = $validated['checkout_token'] ?? session('checkout.token');
        if (! $token) {
            $token = (string) Str::uuid();
            session(['checkout.token' => $token]);
        }
        if ($existing = Order::query()->where('checkout_token', $token)->first()) {
            abort_unless(Gate::allows('viewPlaced', $existing), 403);

            return $this->orderRedirect($placeOrder->initiatePayment($existing));
        }
        abort_unless($token === session('checkout.token'), 419);
        $messages = $this->cartService->revalidateStock();
        $items = $this->cartService->getItems();
        if ($items->isEmpty()) {
            return redirect()->route('cart.show');
        }
        if ($messages !== [] || (session()->has('checkout.review') && session('checkout.review') !== $this->fingerprint($items))) {
            return redirect()->route('checkout.show')->withErrors(['cart' => 'Je winkelwagen of prijzen zijn gewijzigd. Controleer het overzicht en bevestig opnieuw.']);
        }
        $validated = $request->validated();
        $validated['checkout_token'] = $token;
        $validated['reviewed_shipping_cents'] = session('checkout.shipping.'.(int) $validated['shipping_method_id']);
        $country = strtoupper($validated['country_code']);

        try {
            $order = $placeOrder->handle(
                data: $validated,
                cartItems: $items,
                allowedPaymentMethods: $this->paymentMethodsForCountry($country),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return $this->orderRedirect($order);
    }

    private function orderRedirect(Order $order): RedirectResponse
    {
        if ($order->status === 'pending_payment' && $order->paid_at === null && filled(data_get($order->meta, 'redirect_url'))) {
            return redirect()->away(data_get($order->meta, 'redirect_url'));
        }

        return Auth::check() ? redirect()->route('account.orders.show', $order) : redirect()->route('checkout.confirmation', $order);
    }

    /** @param \Illuminate\Support\Collection<int, covariant array<string, mixed>> $items */
    private function fingerprint(\Illuminate\Support\Collection $items): string
    {
        return hash('sha256', $items->map(fn (array $item): array => [
            'id' => $item['id'], 'quantity' => $item['quantity'], 'price' => $item['price'],
        ])->sortBy('id')->values()->toJson());
    }

    public function confirmation(Order $order): Response
    {
        $this->authorizeGuestConfirmation($order);

        $order->load('items');

        return inertia('checkout/confirmation', [
            'order' => OrderResource::make($order),
        ]);
    }

    protected function authorizeGuestConfirmation(Order $order): void
    {
        abort_unless(Gate::allows('viewPlaced', $order), 403, 'Deze bestelling is niet beschikbaar.');
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    protected function paymentMethodsForCountry(string $country): array
    {
        $common = [
            ['id' => 'card', 'label' => 'Creditcard'],
            ['id' => 'paypal', 'label' => 'PayPal'],
            ['id' => 'bank', 'label' => 'Bankoverschrijving'],
        ];

        return match ($country) {
            'NL' => [['id' => 'ideal', 'label' => 'iDEAL'], ...$common],
            'BE' => [['id' => 'bancontact', 'label' => 'Bancontact'], ...$common],
            default => $common,
        };
    }
}
