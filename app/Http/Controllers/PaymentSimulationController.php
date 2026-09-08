<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Order\Actions\MarkOrderPaidAction;
use App\Domain\Payment\PaymentGatewayManager;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Stand-in for a PSP's hosted payment page, so the redirect + callback flow can
 * be walked through locally. Its routes are only registered outside production
 * (see routes/web.php); the guard below covers a misconfigured environment.
 */
class PaymentSimulationController extends Controller
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    public function show(Order $order): Response
    {
        $this->guard($order);

        return inertia('checkout/simulate-payment', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'total_cents' => $order->total_cents,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'can_pay' => $order->canPay(),
                'expires_at' => $order->paymentExpiresAt()?->toIso8601String(),
                'confirmation_url' => route('checkout.confirmation', $order),
                'payment_method' => $order->payment_method,
            ],
        ]);
    }

    public function store(\App\Http\Requests\Checkout\SimulatePaymentRequest $request, Order $order, MarkOrderPaidAction $markPaid): RedirectResponse
    {
        $this->guard($order);

        if ($request->validated('outcome') === 'cancelled') {
            \Illuminate\Support\Facades\DB::transaction(function () use ($order): void {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
                if ($locked->status === 'pending_payment') {
                    app(\App\Domain\Product\Services\StockService::class)->release($locked);
                    $locked->forceFill(['status' => 'cancelled', 'payment_status' => 'cancelled', 'stock_reserved_at' => null])->save();
                }
            });

            return redirect()->route('checkout.confirmation', $order);
        }
        $request->merge(['reference' => $order->payment_reference]);
        // Always the testing driver: this page exists only to stand in for one.
        $result = $this->gateways->driver('testing')->handleCallback($request);

        if (! $result->isPaid()) {
            Order::query()->whereKey($order->id)->where('status', 'pending_payment')->update(['payment_status' => 'failed']);

            return back()->with('status', 'Betaling gesimuleerd als mislukt. De bestelling blijft open staan.');
        }

        $markPaid->handle($order, $result);

        return redirect()
            ->route('checkout.confirmation', $order)
            ->with('status', 'Betaling gesimuleerd. Bestelling staat op betaald.');
    }

    private function guard(Order $order): void
    {
        abort_unless(app()->environment((array) config('payment.simulator_environments', [])), 404);
        abort_if(app()->environment('production'), 404);

        $this->authorize('viewPlaced', $order);
    }
}
