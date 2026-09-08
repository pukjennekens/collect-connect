<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::query()->whereKey($validated['product_id'])->firstOrFail();

        $this->cartService->addItem($product, $validated['quantity']);

        return back();
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$product->stock],
        ]);

        $this->cartService->setQuantity($product, $validated['quantity']);

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->cartService->removeItem($product);

        return back();
    }
}
