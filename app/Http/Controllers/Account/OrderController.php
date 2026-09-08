<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\Order\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Inertia\Response as InertiaResponse;

class OrderController extends Controller
{
    public function index(): InertiaResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);
        $orders = $user->orders()
            ->latest()
            ->paginate(20);

        return inertia('account/orders/index', [
            'orders' => OrderResource::collection($orders),
        ]);
    }

    public function show(Order $order): InertiaResponse
    {
        $this->authorize('view', $order);

        $order->load('items');

        return inertia('account/orders/show', [
            'order' => OrderResource::make($order),
        ]);
    }

    public function invoice(Order $order, \App\Integrations\Bricqer\BricqerConnector $connector): Response
    {
        $this->authorize('view', $order);
        abort_unless($order->invoice_document_id !== null, 404);
        $document = $connector->send(new \App\Integrations\Bricqer\Requests\Commerce\GetDocumentRequest((int) $order->invoice_document_id));

        return response($document->body(), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="invoice-'.$order->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function summary(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load('items');

        $lines = $order->items->map(fn (OrderItem $item): string => sprintf(
            '%s x%d @ €%.2f',
            $item->title,
            $item->quantity,
            $item->unit_price_cents / 100,
        ))->implode("\n");

        $body = "Besteloverzicht {$order->number}\n"
            ."Status: {$order->status}\n"
            ."Klant: {$order->name} <{$order->email}>\n"
            ."Adres: {$order->shipping_line1}, {$order->shipping_postal_code} {$order->shipping_city}, {$order->shipping_country_code}\n"
            ."Verzending: {$order->shipping_method_name}\n"
            .'Tracking: '.($order->tracking_code ?? '—')."\n\n"
            ."Regels:\n{$lines}\n\n"
            .'Subtotaal: €'.number_format($order->subtotal_cents / 100, 2, ',', '.')."\n"
            .'Verzendkosten: €'.number_format($order->shipping_cents / 100, 2, ',', '.')."\n"
            .'Totaal: €'.number_format($order->total_cents / 100, 2, ',', '.')."\n";

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="order-summary-'.$order->number.'.txt"',
        ]);
    }
}
