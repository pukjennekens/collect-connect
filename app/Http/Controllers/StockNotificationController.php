<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Shop\StoreStockNotificationRequest;
use App\Models\Product;
use App\Models\StockNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class StockNotificationController extends Controller
{
    public function store(StoreStockNotificationRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();

        if ($product->stock > 0) {
            return back()->with('status', 'Dit product is al op voorraad.');
        }

        $subscription = StockNotification::query()->firstOrCreate([
            'product_id' => $product->id,
            'email' => strtolower(trim($validated['email'])),
        ]);
        Cache::lock('stock-notification:'.$subscription->id, 120)->block(5, function () use ($subscription): void {
            $subscription->refresh()->update(['notified_at' => null]);
        });

        return back()->with('status', 'We mailen je zodra dit product weer op voorraad is.');
    }
}
